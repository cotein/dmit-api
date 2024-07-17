<?php

namespace App\Src\Afip;

use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Src\Helpers\Afip as AfipHelper;
use App\Exceptions\Afip\FEParamGetPtosVentaException;
use App\Exceptions\Afip\FECompUltimoAutorizadoException;
use Cotein\ApiAfip\Facades\Afip;
use Cotein\ApiAfip\Facades\AfipWebService;
use Exception;
use Illuminate\Support\Facades\Log;

class WSFacturaElectronica
{
    protected $wsfe;

    public function __construct()
    {

        $environment = request()->environment;

        if ($environment === null) {
            $environment = 'testing';
        }

        $this->wsfe = AfipWebService::findWebService('factura', $environment, request()->company_cuit,  request()->company_id,  request()->user_id);
    }
    public function FECompUltimoAutorizado(Request $request)
    {
        try {
            $result =  $this->wsfe->FECompUltimoAutorizado($request->CbteTipo, $request->PtoVta);

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                throw new FECompUltimoAutorizadoException($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (FECompUltimoAutorizadoException $e) {
            activity(Constantes::ERROR_WSFE_ULTIMO_AUTORIZADO)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw $e;
        } catch (\Exception $e) {
            activity(Constantes::ERROR_WSFE_ULTIMO_AUTORIZADO)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw $e;
        }
    }

    public function FEParamGetPtosVenta(Request $request)
    {
        try {
            $result =  $this->wsfe->FEParamGetPtosVenta();

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                throw new FEParamGetPtosVentaException($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (FEParamGetPtosVentaException $e) {
            activity(Constantes::ERROR_WSFE_PTO_VENTA)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw $e;
        } catch (\Exception $e) {
            activity(Constantes::ERROR_WSFE_ULTIMO_AUTORIZADO)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw $e;
        }
    }

    public function FECAESolicitar(Request $request)
    {
        try {
            $result =  $this->wsfe->FECAESolicitar($request->FeCabReq, $request->FECAEDetRequest);

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                activity(Constantes::FECAESolicitar)
                    ->causedBy(auth('api')->user())
                    ->withProperties(json_decode(json_encode($result), true));
                throw new Exception($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (FEParamGetPtosVentaException $e) {
            activity(Constantes::ERROR_WSFE_PTO_VENTA)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw $e;
        } catch (\Exception $e) {
            activity(Constantes::ERROR_WSFE_ULTIMO_AUTORIZADO)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw $e;
        }
    }
}
