<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Cotein\ApiAfip\Facades\AfipWebService;

class AfipPadronController extends Controller
{
    protected $padron;

    public function getCompanyDataByPadron()
    {
        $this->padron = AfipWebService::findWebService('PADRON');
        Log::info($this->padron);
    }

    public function getCompanyDataByConstancia()
    {
        $this->padron = AfipWebService::findWebService('CONSTANCIA');
    }
}
