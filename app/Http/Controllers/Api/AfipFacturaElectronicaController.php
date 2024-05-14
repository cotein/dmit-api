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
     * @param Request $request The request object
     * @return array An array containing the last authorized invoice number
     */
    public function FECompUltimoAutorizado(Request $request)
    {
        $result =  $this->afipWS->FECompUltimoAutorizado($request);

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
     * Solicita un comprobante de egreso electrónico a través de la API de AFIP.
     *
     * @param Request $request Contiene la información necesaria para generar el comprobante,
     * como el tipo de comprobante, el tipo de documento de identificación del emisor, el
     * número de identificación del emisor, el número de serie del comprobante, el total
     * a pagar, los detalles de los conceptos, entre otros.
     *
     * @return array El comprobante generado en formato XML.
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


        $now = Carbon::now();

        LogBatch::startBatch();

        $batch_uuid = $now->timestamp . $now->milli;

        $activity = [
            'log_name' => 'SOLICITUD DE FACTURA ELECTRONICA',
            'description' => 'SE UTILIZA WSFEV1 DE AFIP',
            'subject_type' => null,
            'subject_id' => null,
            'causer_type' => 'App\Models\User;',
            'causer_id' => auth()->user()->id,
            'company_id' => $request->company_id,
            'properties' => $request->all(),
            'batch_uuid' => $batch_uuid
        ];

        ActivityLog::save($activity);

        $result =  $this->afipWS->FECAESolicitar($request);

        $invoiceData = [
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
            'parent' => ($request->has('parent')) ? $request->parent : null,
            'result' => $result
        ];

        $reject = json_decode(json_encode($result), true);
        /*  Log::alert($reject);
        print_r($reject); */
        /* if ($reject['FECAESolicitarResult']['FeCabResp']['Resultado'] === 'R') {
            if (isset($reject['FECAESolicitarResult']['FECAEDetResponse'][0]['Observaciones'])) {

                $observaciones = $reject['FECAESolicitarResult']['FECAEDetResponse'][0]['Observaciones'];

                $activity['log_name'] = Constantes::FECAESolicitar;

                $activity['properties'] = $reject;

                ActivityLog::save($activity);

                throw new Exception($observaciones['Obs'][0]['Msg']);
            }
        } */
        if ($reject['FECAESolicitarResult']['FeCabResp']['Resultado'] === 'R') {

            if (isset($reject['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['Observaciones'])) {

                $observaciones = $reject['FECAESolicitarResult']['FeDetResp']['FECAEDetResponse'][0]['Observaciones']['Obs'];
                $mensajes = array_map(function ($observacion) {
                    return $observacion['Msg'];
                }, $observaciones);

                $activity['log_name'] = Constantes::FECAESolicitar;

                $activity['properties'] = $reject;

                ActivityLog::save($activity);

                throw new Exception(implode(', ', $mensajes));
            }
        }

        $invoice = CreatedInvoice::dispatch($invoiceData);

        $activity['log_name'] = 'RESULTADO DE FACTURA ELECTRONICA';
        $activity['properties'] = $result;

        ActivityLog::save($activity);

        LogBatch::getUuid(); // save batch id to retrieve activities later
        LogBatch::endBatch();

        $data = [
            'CbteTipo' => $invoiceData['FeCabReq']['CbteTipo'],
            'invoice' => $invoice
        ];

        return response()->json($data, 201);
    }
}
