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

            $user = (auth()->user()) ? auth()->user() : User::find(1);

            $this->padron = Afip::findWebService('constancia', 'PRODUCTION', $user->company->afip_number, $user->company->id, $user->id);

            $consulta =  [
                'token' => $this->padron->token,
                'sign'  => $this->padron->sign,
                'cuitRepresentada'  => $this->padron->cuitRepresentada,
                'idPersona'         => request()->cuit
            ];

            return $this->padron->getPersona($consulta);
        } catch (\Exception $e) {
            $date = new Carbon();
            Log::alert('Fecha ' . $date->now() . ' code' . $e->getCode(), ' message ' . $e->getMessage());

            throw $e;
        }
    }
}
