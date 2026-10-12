<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\PlanUsageReservation;
use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Events\CreatedInvoice;
use App\Src\Afip\ArcaResponse;
use App\Src\Helpers\ActivityLog;
use App\Src\Services\Plans\PlanGate;
use App\Http\Controllers\Controller;
use App\Src\Afip\WSFacturaElectronica;
use Spatie\Activitylog\Facades\LogBatch;
use App\Exceptions\Afip\AfipRejectionException;
use App\Exceptions\Afip\FEParamGetPtosVentaException;
use Throwable;

class AfipFacturaElectronicaController extends Controller
{

    protected $afipWS;

    public function __construct(WSFacturaElectronica $afipWS, private readonly PlanGate $planGate)
    {
        $this->afipWS = $afipWS;
    }

    /**
     * Returns the last authorized invoice number.
     *
     * @return array An array containing the last authorized invoice number
     */
    public function FECompUltimoAutorizado(Request $request)
    {
        $CbteTipo = $request->CbteTipo;
        $PtoVta = $request->PtoVta;

        $result =  $this->afipWS->FECompUltimoAutorizado($CbteTipo, $PtoVta);

        return response()->json($result, 200);
    }

    /**
     * Returns the available points of sale for the user.
     *
     * @param Request $request The request object
     * @return array An array containing the available points of sale
     * @throws FEParamGetPtosVentaException If the request fails
     */
    public function FEParamGetPtosVenta(Request $request)
    {
        $result = $this->afipWS->FEParamGetPtosVenta($request);

        return response()->json($result, 200);
    }

    /**
     * Handle the request to solicit FECAE (Factura Electronica Comprobante Autorizado Electrónico).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function FECAESolicitar(Request $request)
    {
        $this->validate($request, [
            'FeCabReq' => 'required',
            'FECAEDetRequest' => 'required',
            'environment' => 'required',
            'company_cuit' => 'required',
            'company_id' => 'required',
            'user_id' => 'required',
            'saleCondition' => 'required',
            'paymentType' => 'required',
            'customer' => 'required',
            'products' => 'required|array|min:1',
        ]);

        $invoiceData = $this->prepareInvoiceData($request);

        // El corte por tope, ANTES de llamar al organismo. La reserva se toma acá y
        // se confirma recién cuando el comprobante existe; si ARCA rechaza, el lugar
        // se libera y no se consume nada.
        $reservation = $this->reservarLugar($request);

        if ($request->isMiPyme) {
            return $this->solicitarFacturaDeCredito($request, $invoiceData, $reservation);
        }

        return $this->solicitarComprobante($request, $invoiceData, $reservation);
    }

    /**
     * Toma el lugar del contador antes de llamar al organismo.
     *
     * Devuelve null cuando no hay nada que cortar: el interruptor del CUIT está
     * apagado, el plan no tiene tope para este recurso, o el comprobante es una nota
     * de crédito o débito (que son la única salida para corregir un error y por eso
     * nunca se bloquean ni consumen).
     *
     * La clave de idempotencia se arma con lo que identifica al comprobante, así el
     * reintento del mismo pedido no consume dos veces ni emite dos veces.
     */
    private function reservarLugar(Request $request): ?PlanUsageReservation
    {
        $resource = $this->planGate->resourceForVoucherCode($request->FeCabReq['CbteTipo'] ?? null);

        if ($resource === null) {
            return null;
        }

        $companyId = (int) $request->company_id;

        $clave = $this->planGate->keyFor([
            'company_id' => $companyId,
            'pto_vta' => $request->FeCabReq['PtoVta'] ?? null,
            'cbte_tipo' => $request->FeCabReq['CbteTipo'] ?? null,
            'cbte_fch' => $request->FECAEDetRequest['CbteFch'] ?? null,
            'imp_total' => $request->FECAEDetRequest['ImpTotal'] ?? null,
            'cliente' => $request->customer['cuit'] ?? ($request->customer['id'] ?? null),
            'items' => count($request->products ?? []),
        ]);

        return $this->planGate->reserve($companyId, $resource, $clave);
    }

    /**
     * Registra el consumo cuando el comprobante ya existe, o libera el lugar cuando
     * la emisión no terminó en comprobante.
     *
     * Ojo con el orden: el `catch` cubre tanto el rechazo de ARCA como un fallo al
     * guardar la factura. En los dos casos el lugar vuelve, porque no hay
     * comprobante que contar.
     *
     * @param  callable():mixed  $emision  devuelve el comprobante creado (array con 'id') o null
     */
    private function liquidarLugar(?PlanUsageReservation $reservation, callable $emision): mixed
    {
        try {
            $resultado = $emision();
        } catch (Throwable $e) {
            $this->planGate->release($reservation);

            throw $e;
        }

        $this->planGate->confirm($reservation, $this->idDeComprobante($resultado));

        return $resultado;
    }

    /**
     * El id del comprobante recién creado, para dejarlo anotado en la reserva.
     *
     * @param  mixed  $comprobante
     */
    private function idDeComprobante($comprobante): ?int
    {
        if (is_array($comprobante) && isset($comprobante['id'])) {
            return (int) $comprobante['id'];
        }

        if (is_object($comprobante) && isset($comprobante->id)) {
            return (int) $comprobante->id;
        }

        return null;
    }

    /**
     * Factura de Crédito Electrónica MiPyME (WSFECRED): el frontend ya confirmó el comprobante.
     *
     * @param  array<string, mixed>  $invoiceData
     * @return \Illuminate\Http\JsonResponse
     */
    private function solicitarFacturaDeCredito(Request $request, array $invoiceData, ?PlanUsageReservation $reservation = null)
    {
        $invoiceResult = $this->afipWS->FECAESolicitar($request->all());

        $invoiceData['result'] = json_decode(json_encode($invoiceResult), true);

        $arca = ArcaResponse::fromFecaesolicitarResult($invoiceData['result']);

        $this->registrarRespuestaDeArca($request, $invoiceData['result']);

        // El consumo se registra con el comprobante ya creado, o el lugar vuelve si
        // ARCA rechazó (sin CAE no hay factura y no hay nada que contar).
        $invoice = $this->liquidarLugar($reservation, function () use ($arca, $request, $invoiceData) {
            // Si ARCA no autorizó, no se registra nada: el usuario tiene que ver el motivo y el
            // comprobante no existe (sin CAE no hay factura).
            $this->abortarSiArcaRechazo($arca, $request);

            return CreatedInvoice::dispatch($invoiceData);
        });

        return response()->json([
            'CbteTipo' => $invoiceData['FeCabReq']['CbteTipo'],
            'invoice' => $invoice,
            'arca' => $arca->toArray(),
            'arcaEvents' => $arca->mensajes(),
        ], 201);
    }

    /**
     * Comprobante por WSFE v1 (facturas y notas A, B y C).
     *
     * @param  array<string, mixed>  $invoiceData
     * @return \Illuminate\Http\JsonResponse
     */
    private function solicitarComprobante(Request $request, array $invoiceData, ?PlanUsageReservation $reservation = null)
    {
        $clonedRequest = $this->sinFechasDeServicioSiEsProducto($request->all());

        $date = Carbon::parse($request->FECAEDetRequest['CbteFch'])->format('Y-m-d');

        $montoObligadoRecepcion = $this->afipWS->consultarMontoObligadoRecepcion($request->customer['cuit'], $date);

        $array = json_decode(json_encode($montoObligadoRecepcion->consultarMontoObligadoRecepcionReturn), true);

        if ($array['obligado'] === 'S' && $request->FECAEDetRequest['ImpTotal'] >= (float)$array['montoDesde']) {

            $clonedRequest['FeCabReq']['CbteTipo'] = $this->getCbteTipo($request->FeCabReq['CbteTipo']);

            $ultimoAutorizado = $this->afipWS->FECompUltimoAutorizado($clonedRequest['FeCabReq']['CbteTipo'], $request->FeCabReq['PtoVta']);

            $clonedRequest['FECAEDetRequest']['CbteDesde'] = $ultimoAutorizado->FECompUltimoAutorizadoResult->CbteNro + 1;

            $clonedRequest['FECAEDetRequest']['CbteHasta'] = $ultimoAutorizado->FECompUltimoAutorizadoResult->CbteNro + 1;

            // Este camino NO emite: le pide al frontend que confirme el comprobante
            // como Factura de Crédito MiPyME. No hay comprobante que contar todavía,
            // así que el lugar vuelve y se toma de nuevo cuando el usuario confirme.
            $this->planGate->release($reservation);

            return response()->json([
                'isMipyme' => true,
                'CbteTipo' => $clonedRequest['FeCabReq']['CbteTipo'],
                'CbteDesde' => $clonedRequest['FECAEDetRequest']['CbteDesde'],
                'CbteHasta' => $clonedRequest['FECAEDetRequest']['CbteHasta']
            ], 200);
        }

        $ultAutorizado = $this->afipWS->FECompUltimoAutorizado($clonedRequest['FeCabReq']['CbteTipo'], $request->FeCabReq['PtoVta']);

        $array = json_decode(json_encode($ultAutorizado), true);

        $clonedRequest['FECAEDetRequest']['CbteDesde'] = $array['FECompUltimoAutorizadoResult']['CbteNro'] + 1;
        $clonedRequest['FECAEDetRequest']['CbteHasta'] = $array['FECompUltimoAutorizadoResult']['CbteNro'] + 1;
        $now = Carbon::now();
        LogBatch::startBatch();
        $batch_uuid = $now->timestamp . $now->milli;

        $activity = [
            'log_name' => 'SOLICITUD DE FACTURA ELECTRONICA',
            'description' => 'SE UTILIZA WSFEV1 DE AFIP',
            'causer_type' => 'App\Models\User',
            'causer_id' => auth()->user()->id,
            'company_id' => $request->company_id,
            'properties' => $request->all(),
            'batch_uuid' => $batch_uuid
        ];
        ActivityLog::save($activity);

        $result = $this->afipWS->FECAESolicitar($clonedRequest);

        $invoiceData['result'] = json_decode(json_encode($result), true);

        $arca = ArcaResponse::fromFecaesolicitarResult($invoiceData['result']);

        $activity['log_name'] = 'RESULTADO DE FACTURA ELECTRONICA';
        $activity['properties'] = $result;
        ActivityLog::save($activity);

        // Igual que en MiPyme: si ARCA rechazó, la factura no se guarda y el motivo viaja al usuario.
        try {
            // El consumo del plan se registra con el comprobante ya creado. Si ARCA
            // rechaza, `liquidarLugar` libera el lugar: sin comprobante no se cuenta.
            $invoice = $this->liquidarLugar($reservation, function () use ($arca, $request, $invoiceData) {
                $this->abortarSiArcaRechazo($arca, $request);

                return CreatedInvoice::dispatch($invoiceData);
            });
        } finally {
            LogBatch::getUuid();
            LogBatch::endBatch();
        }

        return response()->json([
            'CbteTipo' => $invoiceData['FeCabReq']['CbteTipo'],
            'invoice' => $invoice,
            'arca' => $arca->toArray(),
            'arcaEvents' => $arca->mensajes(),
        ], 201);
    }

    /**
     * Quita las fechas de servicio cuando el comprobante es de Concepto 1 (productos).
     *
     * ARCA rechaza el comprobante con 10049 ("FchVtoPago Debe informarse solo si Concepto es
     * igual a 2 o 3") si se informan. El frontend las manda igual, porque el vencimiento de
     * pago sale de la condición de venta, así que se limpian acá para que pueda autorizarse.
     * Para las Facturas de Crédito Electrónica MiPyME (FCE) no aplica: ahí FchVtoPago es
     * obligatorio incluso con Concepto 1, y ese flujo no pasa por este método.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function sinFechasDeServicioSiEsProducto(array $request): array
    {
        $concepto = (int) ($request['FECAEDetRequest']['Concepto'] ?? 0);

        if ($concepto !== 1) {
            return $request;
        }

        unset(
            $request['FECAEDetRequest']['FchServDesde'],
            $request['FECAEDetRequest']['FchServHasta'],
            $request['FECAEDetRequest']['FchVtoPago'],
        );

        return $request;
    }

    /**
     * Corta la operación cuando ARCA no autorizó el comprobante.
     *
     * Antes se guardaba igual la factura con el número que ARCA devolvía en el eco y sin CAE,
     * y el frontend mostraba "Factura generada correctamente": el usuario nunca veía el motivo
     * y el último comprobante autorizado seguía siendo 0 (todas las facturas salían con 1).
     *
     * @throws AfipRejectionException
     */
    private function abortarSiArcaRechazo(ArcaResponse $arca, Request $request): void
    {
        if (! $arca->isRejected()) {
            return;
        }

        $this->registrarRechazo($arca, $request);

        throw new AfipRejectionException($arca);
    }

    /**
     * Registra el rechazo con la respuesta completa de ARCA (auditoría).
     */
    private function registrarRechazo(ArcaResponse $arca, Request $request): void
    {
        $activity = [
            'log_name' => Constantes::FECAESolicitar,
            'description' => 'ARCA RECHAZO EL COMPROBANTE: ' . implode(' | ', $arca->mensajes()),
            'causer_type' => 'App\Models\User',
            'causer_id' => auth()->user()->id,
            'company_id' => $request->company_id,
            'properties' => collect($arca->raw())->toJson(),
            'batch_uuid' => ''
        ];

        ActivityLog::save($activity);
    }

    /**
     * @param  mixed  $result
     */
    private function registrarRespuestaDeArca(Request $request, $result): void
    {
        $activity = [
            'log_name' => 'RESULTADO DE FACTURA ELECTRONICA',
            'description' => 'SE UTILIZA WSFECRED DE AFIP',
            'causer_type' => 'App\Models\User',
            'causer_id' => auth()->user()->id,
            'company_id' => $request->company_id,
            'properties' => $result,
            'batch_uuid' => ''
        ];

        ActivityLog::save($activity);
    }

    /**
     * Prepares the invoice data for processing.
     *
     * @param mixed $request The request data.
     * @return void
     */
    private function prepareInvoiceData($request)
    {
        return [
            'FeCabReq' => $request->FeCabReq,
            'FECAEDetRequest' => $request->FECAEDetRequest,
            'environment' => $request->environment,
            'company_cuit' => $request->company_cuit,
            'company_id' => $request->company_id,
            'user_id' => $request->user_id,
            'products' => $request->products,
            'saleCondition' => $request->saleCondition,
            'paymentType' => $request->paymentType,
            'customer' => $request->customer,
            'comments' => $request->comments,
            'parent' => $request->has('parent') ? $request->parent : null,
        ];
    }

    /**
     * Retrieves the CbteTipo based on the given $cbteTipo.
     *
     * @param int $cbteTipo The CbteTipo to retrieve.
     * @return mixed The retrieved CbteTipo.
     */
    private function getCbteTipo($cbteTipo)
    {
        $types = [
            1 => Constantes::WSFECRED['FCA'],
            2 => Constantes::WSFECRED['NDA'],
            3 => Constantes::WSFECRED['NCA'],
            6 => Constantes::WSFECRED['FCB'],
            7 => Constantes::WSFECRED['NDB'],
            8 => Constantes::WSFECRED['NCB'],
            11 => Constantes::WSFECRED['FCC'],
            12 => Constantes::WSFECRED['NDC'],
            13 => Constantes::WSFECRED['NCC'],
        ];
        return $types[(int)$cbteTipo] ?? $cbteTipo;
    }
}
