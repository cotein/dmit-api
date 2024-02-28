<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Cotein\ApiAfip\Facades\Afip;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class AfipPadronController extends Controller
{
    protected $padron;

    public function getCompanyDataByPadron()
    {
        try {
            $ws = 'constancia';

            if (strlen((string) request()->cuit) < 11) {
                $ws = 'padron';
            }

            $user = (auth()->user()) ? auth()->user() : User::find(1);

            if (!$user->company) {

                $this->padron = Afip::findWebService($ws, 'PRODUCTION', 20227339730, 1, 1);
            } else {

                $this->padron = Afip::findWebService($ws, 'PRODUCTION', $user->company->afip_number, $user->company->id, $user->id);
            }

            if ($ws === 'constancia') {
                return $this->padron->getPersona(request()->cuit);
            }

            if ($ws === 'padron') {
                return $this->padron->getPersonaByDocumento(request()->cuit);
            }
        } catch (\Exception $e) {
            $date = new Carbon();
            Log::alert('Fecha ' . $date->now() . ' code' . $e->getCode() . ' message ' . $e->getMessage());

            throw $e;
        }
    }
}
