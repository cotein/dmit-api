<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Src\Constantes;
use Carbon\Carbon;
use Cotein\ApiAfip\Facades\AfipWebService;
use Illuminate\Http\Request;

class AfipPadronController extends Controller
{
    protected $padron;

    public function getCompanyDataByPadron(Request $request)
    {
        $request->validate([
            'cuit' => 'required|numeric',
        ]);

        try {
            $ws = $this->selectWebService($request->cuit);

            $user = auth()->user();

            if (!$user) {
                throw new \Exception('El usuario no está autenticado');
            }

            $this->padron = $this->getPadron($ws, $user);

            return $this->getPersonaData($ws, $request->cuit);
        } catch (\Exception $e) {
            $date = new Carbon();

            throw $e;
        }
    }

    protected function selectWebService($cuit)
    {
        return strlen((string) $cuit) < 11 ? 'padron' : 'constancia';
    }

    protected function getPadron($ws, User $user)
    {
        if (!$user->company) {
            return AfipWebService::findWebService($ws, Constantes::PRODUCTION_ENVIRONMENT, Constantes::DIEGO_BARRUETA_CUIT, 1, 1);
        }
        return AfipWebService::findWebService($ws, Constantes::PRODUCTION_ENVIRONMENT, $user->company->afip_number, $user->company->id, $user->id);
    }

    protected function getPersonaData($ws, $cuit)
    {
        if ($ws === 'constancia') {
            return $this->padron->getPersona($cuit);
        }

        if ($ws === 'padron') {
            return $this->padron->getPersonaByDocumento($cuit);
        }
    }
}
