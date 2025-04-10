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

    protected $wsFeCred;

    public function __construct()
    {

        $environment = request()->environment;

        if ($environment === null) {
            $environment = 'testing';
        }

        $this->wsfe = AfipWebService::findWebService('factura', $environment, request()->company_cuit,  request()->company_id,  request()->user_id);

        $this->wsFeCred = AfipWebService::findWebService('WSFECRED', 'production', request()->company_cuit,  request()->company_id,  request()->user_id);
    }

    public function FECompUltimoAutorizado($CbteTipo, $PtoVta)
    {
        try {
            $result =  $this->wsfe->FECompUltimoAutorizado($CbteTipo, $PtoVta);

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                throw new FECompUltimoAutorizadoException($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (FECompUltimoAutorizadoException $e) {
            /* activity(Constantes::ERROR_WSFE_ULTIMO_AUTORIZADO)
                ->causedBy(auth('api')->user())
                ->log($e->getMessage()); */

            throw $e;
        } catch (\Exception $e) {
            /* activity(Constantes::ERROR_WSFE_ULTIMO_AUTORIZADO)
                ->causedBy(auth('api')->user())
                ->log($e->getMessage()); */

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

    public function FECAESolicitar(array $FECAESolicitarArray)
    {
        try {
            $result =  $this->wsfe->FECAESolicitar($FECAESolicitarArray['FeCabReq'], $FECAESolicitarArray['FECAEDetRequest']);

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                activity(Constantes::FECAESolicitar)
                    ->causedBy(auth('api')->user())
                    ->withProperties(json_decode(json_encode($result), true));
                throw new Exception($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (FEParamGetPtosVentaException $e) {
            /* activity(Constantes::ERROR_WSFE_PTO_VENTA)
                ->causedBy(auth('api')->user())
                ->withProperties($FECAESolicitarArray)
                ->log($e->getMessage()); */

            throw $e;
        } catch (\Exception $e) {
            /* activity(Constantes::ERROR_WSFE_ULTIMO_AUTORIZADO)
                ->causedBy(auth('api')->user())
                ->withProperties($FECAESolicitarArray)
                ->log($e->getMessage()); */

            throw $e;
        }
    }

    public function consultarMontoObligadoRecepcion($cuitConsultada, $fechaEmision)
    {
        try {
            $result =  $this->wsFeCred->consultarMontoObligadoRecepcion($cuitConsultada, $fechaEmision);

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                activity('consultarMontoObligadoRecepcion')
                    ->causedBy(auth('api')->user())
                    ->withProperties(json_decode(json_encode($result), true));
                throw new Exception($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (Exception $e) {
            activity('consultarMontoObligadoRecepcion')
                ->causedBy(auth('api')->user())
                ->withProperties([
                    'cuitConsultada' => $cuitConsultada,
                    'fechaEmision' => $fechaEmision
                ])
                ->log($e->getMessage());

            throw $e;
        }
    }

    public function FEParamGetTiposTributos()
    {
        try {
            $result =  $this->wsfe->FEParamGetTiposTributos();

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                activity('FEParamGetTiposTributos')
                    ->causedBy(auth('api')->user())
                    ->withProperties(json_decode(json_encode($result), true));
                throw new Exception($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (Exception $e) {
            activity('FEParamGetTiposTributos')
                ->causedBy(auth('api')->user())
                ->log($e->getMessage());

            throw $e;
        }
    }

    public function FEParamGetCondicionIvaReceptor()
    {
        try {
            $result =  $this->wsfe->FEParamGetCondicionIvaReceptor();

            $errors = AfipHelper::getErrValues($result);

            if ($errors) {
                activity('FEParamGetCondicionIvaReceptor')
                    ->causedBy(auth('api')->user())
                    ->withProperties(json_decode(json_encode($result), true));
                throw new Exception($errors[0]['Msg'], $errors[0]['Code']);
            }

            return $result;
        } catch (Exception $e) {
            activity('FEParamGetCondicionIvaReceptor')
                ->causedBy(auth('api')->user())
                ->log($e->getMessage());

            throw $e;
        }
    }
}
