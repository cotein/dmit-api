<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    $c = AfipWebService::findWebService('WSFECRED', 'testing', 20227339730, 1, 1);
    dd($c->consultarMontoObligadoRecepcion(30546689979, '2024-07-14'));
    dd($c->Dummy());
    return 'funciona';
});
