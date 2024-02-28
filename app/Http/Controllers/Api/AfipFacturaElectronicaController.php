<?php

namespace App\Http\Controllers\Api;

use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Events\CreatedInvoice;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Src\Afip\WSFacturaElectronica;
use App\Src\Helpers\Afip as HelpersAfip;
use App\Exceptions\Afip\FEParamGetPtosVentaException;
use Exception;

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
            'customer' => 'required',
            'voucher_id' => 'required',
            'products' => 'required|array|min:1',
        ]);

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
            'customer' => $request->customer,
            'comments' => $request->comments,
            'parent' => ($request->has('parent')) ? $request->parent : null,
            'result' => $result
        ];

        /* $reject = json_decode(json_encode($result), true);

        if ($reject['FECAESolicitarResult']['FeCabResp']['Resultado'] === 'R') {
            if (isset($reject['FECAESolicitarResult']['FECAEDetResponse'][0]['Observaciones'])) {
                activity(Constantes::FECAESolicitar)
                    ->causedBy(auth('api')->user())
                    ->withProperties($reject);
                $observaciones = $reject['FECAESolicitarResult']['FECAEDetResponse'][0]['Observaciones'];
                throw new Exception($observaciones['Obs'][0]['Msg']);
            }
        } */

        $invoice = CreatedInvoice::dispatch($invoiceData);

        $data = [
            'CbteTipo' => $invoiceData['FeCabReq']['CbteTipo'],
            'invoice' => $invoice
        ];

        return response()->json($data, 201);
    }
}
