<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Cotein\ApiAfip\Facades\AfipWebService;
use Illuminate\Http\Request;

class ArbaController extends Controller
{
    protected $arbaWs;

    public function __construct()
    {
        $this->arbaWs = AfipWebService::findWebService('ARBA', 'testing', request()->company_cuit,  request()->company_id,  request()->user_id);
    }

    public function alicuota_por_sujeto(Request $request)
    {
        $cuit = $request->cuit;

        try {
            $alicuota = $this->arbaWs->alicuota_sujeto($cuit);

            //$alicuotaPercepcion = (string) $alicuota->contribuyentes->contribuyente->alicuotaPercepcion;
        } catch (\Exception $e) {

            throw new \Exception($e->getMessage(), $e->getCode());
        }

        return response()->json($alicuota, 200);
    }
}
