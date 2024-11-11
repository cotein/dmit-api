<?php

namespace App\Http\Controllers\Api;

use Exception;
use Carbon\Carbon;
use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Events\CreatedInvoice;
use App\Src\Helpers\ActivityLog;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Src\Afip\WSFacturaElectronica;
use Spatie\Activitylog\Facades\LogBatch;
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
            $result = $this->afipWS->FECAESolicitar($request->all());

            $invoiceData['result'] = json_decode(json_encode($result), true);

            if ($this->isRejected($invoiceData['result'])) {
                $this->handleRejection($invoiceData['result'], $request);
            }

            $invoice = CreatedInvoice::dispatch($invoiceData);

            return response()->json(['CbteTipo' => $invoiceData['FeCabReq']['CbteTipo'], 'invoice' => $invoice], 201);
        }

        $clonedRequest = $request->all();

        $date = Carbon::parse($request->FECAEDetRequest['CbteFch'])->format('Y-m-d');

        $result = $this->afipWS->consultarMontoObligadoRecepcion($request->customer['cuit'], $date);

        $array = json_decode(json_encode($result->consultarMontoObligadoRecepcionReturn), true);

        if ($array['obligado'] === 'S' && $request->FECAEDetRequest['ImpTotal'] >= (float)$array['montoDesde']) {
            $clonedRequest['FeCabReq']['CbteTipo'] = $this->getCbteTipo($request->FeCabReq['CbteTipo']);
            $result = $this->afipWS->FECompUltimoAutorizado($clonedRequest['FeCabReq']['CbteTipo'], $request->FeCabReq['PtoVta']);
            $clonedRequest['FECAEDetRequest']['CbteDesde'] = $result->FECompUltimoAutorizadoResult->CbteNro + 1;
            $clonedRequest['FECAEDetRequest']['CbteHasta'] = $result->FECompUltimoAutorizadoResult->CbteNro + 1;

            return response()->json([
                'isMipyme' => true,
                'CbteTipo' => $clonedRequest['FeCabReq']['CbteTipo'],
                'CbteDesde' => $clonedRequest['FECAEDetRequest']['CbteDesde'],
                'CbteHasta' => $clonedRequest['FECAEDetRequest']['CbteHasta']
            ], 200);
        }

        $invoice = $this->processNonMiPymeRequest($clonedRequest, $request, $invoiceData);

        return response()->json(['CbteTipo' => $invoiceData['FeCabReq']['CbteTipo'], 'invoice' => $invoice], 201);
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
     * Checks if the result of a process is rejected.
     *
     * @param mixed $result The result of the process.
     * @return bool Returns true if the result is rejected, false otherwise.
     */
    private function isRejected($result)
    {
        return $result['FECAESolicitarResult']['FeCabResp']['Resultado'] === 'R';
    }

    private function handleRejection($result, $request)
    {
        if (isset($result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['Observaciones'])) {
            $observaciones = $result['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['Observaciones']['Obs'];
            $mensajes = array_map(fn($obs) => $obs['Msg'], $observaciones);

            $activity = [
                'log_name' => Constantes::FECAESolicitar,
                'description' => collect($mensajes)->toJson(),
                'causer_type' => 'App\Models\User',
                'causer_id' => auth()->user()->id,
                'company_id' => $request->company_id,
                'properties' => collect($request->all())->toJson(),
                'batch_uuid' => ''
            ];
            ActivityLog::save($activity);

            throw new Exception(implode(', ', $mensajes));
        }
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

    /**
     * Process a non-MiPyme request.
     *
     * @param mixed $clonedRequest The cloned request object.
     * @param mixed $request The original request object.
     * @param array $invoiceData The invoice data.
     * @return void
     */
    private function processNonMiPymeRequest($clonedRequest, $request, $invoiceData)
    {
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

        if ($this->isRejected($invoiceData['result'])) {
            $this->handleRejection($invoiceData['result'], $request);
        }

        $invoice = CreatedInvoice::dispatch($invoiceData);

        $activity['log_name'] = 'RESULTADO DE FACTURA ELECTRONICA';
        $activity['properties'] = $result;
        ActivityLog::save($activity);

        LogBatch::getUuid();
        LogBatch::endBatch();
        return $invoice;
    }
}
