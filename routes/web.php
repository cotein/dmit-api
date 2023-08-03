<?php

use App\Http\Controllers\Api\AfipPadronController;
use Cotein\ApiAfip\Afip\WS\WSCONSTANCIAINSCRIPCION;
use Cotein\ApiAfip\Afip\WS\WSPUC13;
use Cotein\ApiAfip\Facades\Afip;
use Illuminate\Support\Facades\Route;

Route::middleware(['cors'])->group(function () {
    Route::get('/', function () {

        $a = Afip::findWebService('CONSTANCIA', 'production', 20227339730, 1, 1);
        //$a = new WSCONSTANCIAINSCRIPCION('production', 20227339730, 1, 1);
        dd($a->functions());
        $pp = new AfipPadronController();
        $r = $pp->getCompanyDataByPadron();
        dd($r);
        return view('welcome');
    });
});
