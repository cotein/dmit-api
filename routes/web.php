<?php

use App\Models\SaleInvoices;
use Carbon\Carbon;
use Cotein\ApiAfip\Models\AfipToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $www = AfipToken::where('ws', 'WSFE')
        ->where('active', true)
        ->where('environment', 'PRODUCTION')
        ->where('company_id', 1)->get();

    //dd($www);

    $c =  new Carbon();
    $currentTime    = $c->parse($c->now());
    $expirationTime = $c->parse('2024-02-28 04:08:44');
    dd($currentTime->gt($expirationTime));
    /*

        if (strtotime($currentTime) >= strtotime($expirationTime)) {
            return false;
        } */
});
