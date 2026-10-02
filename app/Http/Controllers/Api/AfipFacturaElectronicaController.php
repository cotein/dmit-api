<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Events\CreatedInvoice;
use App\Src\Afip\ArcaResponse;
use App\Src\Helpers\ActivityLog;
use App\Http\Controllers\Controller;
use App\Src\Afip\WSFacturaElectronica;
use Spatie\Activitylog\Facades\LogBatch;
use App\Exceptions\Afip\AfipRejectionException;
use App\Exceptions\Afip\FEParamGetPtosVentaException;

class AfipFacturaElectronicaController extends Controller
{

    protected $afipWS;

    public function __construct(WSFacturaElectronica $afipWS)
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

        if ($request->isMiPyme) {
            return $this->solicitarFacturaDeCredito($request, $invoiceData);
        }

        return $this->solicitarComprobante($request, $invoiceData);
    }

    /**
     * Factura de Crédito Electrónica MiPyME (WSFECRED): el frontend ya confirmó el comprobante.
     *
     * @param  array<string, mixed>  $invoiceData
     * @return \Illuminate\Http\JsonResponse
     */
    private function solicitarFacturaDeCredito(Request $request, array $invoiceData)
    {
        $invoiceResult = $this->afipWS->FECAESolicitar($request->all());

        $invoiceData['result'] = json_decode(json_encode($invoiceResult), true);

        $arca = ArcaResponse::fromFecaesolicitarResult($invoiceData['result']);

        $this->registrarRespuestaDeArca($request, $invoiceData['result']);

        // Si ARCA no autorizó, no se registra nada: el usuario tiene que ver el motivo y el
        // comprobante no existe (sin CAE no hay factura).
        $this->abortarSiArcaRechazo($arca, $request);

        $invoice = CreatedInvoice::dispatch($invoiceData);

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
    private function solicitarComprobante(Request $request, array $invoiceData)
    {
        $clonedRequest = $request->all();

        $date = Carbon::parse($request->FECAEDetRequest['CbteFch'])->format('Y-m-d');

        $montoObligadoRecepcion = $this->afipWS->consultarMontoObligadoRecepcion($request->customer['cuit'], $date);

        $array = json_decode(json_encode($montoObligadoRecepcion->consultarMontoObligadoRecepcionReturn), true);

        if ($array['obligado'] === 'S' && $request->FECAEDetRequest['ImpTotal'] >= (float)$array['montoDesde']) {

            $clonedRequest['FeCabReq']['CbteTipo'] = $this->getCbteTipo($request->FeCabReq['CbteTipo']);

            $ultimoAutorizado = $this->afipWS->FECompUltimoAutorizado($clonedRequest['FeCabReq']['CbteTipo'], $request->FeCabReq['PtoVta']);

            $clonedRequest['FECAEDetRequest']['CbteDesde'] = $ultimoAutorizado->FECompUltimoAutorizadoResult->CbteNro + 1;

            $clonedRequest['FECAEDetRequest']['CbteHasta'] = $ultimoAutorizado->FECompUltimoAutorizadoResult->CbteNro + 1;

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
            $this->abortarSiArcaRechazo($arca, $request);

            $invoice = CreatedInvoice::dispatch($invoiceData);
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
