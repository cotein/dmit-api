<?php

use App\Src\Arba\Cot\Transport\CurlCotTransport;

return [

    /*
    |--------------------------------------------------------------------------
    | Endpoints del servicio de Transporte de Bienes de ARBA
    |--------------------------------------------------------------------------
    |
    | El path es el mismo en los dos ambientes; cambia el host. El de producción
    | va por HTTPS y el de testing, según el instructivo, es HTTP plano: por eso
    | la verificación del certificado se controla aparte y no se apaga a lo bruto
    | —desactivarla en producción dejaría la clave de la CIT expuesta a un
    | intermediario—.
    |
    */

    'endpoints' => [
        'testing' => env('COT_ENDPOINT_TESTING', 'http://cot.test.arba.gov.ar/TransporteBienes/SeguridadCliente/presentarRemitos.do'),
        'production' => env('COT_ENDPOINT_PRODUCTION', 'https://cot.arba.gov.ar/TransporteBienes/SeguridadCliente/presentarRemitos.do'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiempos de espera
    |--------------------------------------------------------------------------
    |
    | El timeout del módulo de ARBA que ya existía era de 4 segundos y sirve
    | para una consulta chica, no para subir un archivo de remitos: con 4
    | segundos cualquier envío real corta por timeout y queda la duda de si ARBA
    | lo procesó o no.
    |
    */

    'timeout' => (int) env('COT_TIMEOUT', 60),

    'connect_timeout' => (int) env('COT_CONNECT_TIMEOUT', 15),

    'verify_ssl' => filter_var(env('COT_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Implementación del transporte
    |--------------------------------------------------------------------------
    |
    | Está en configuración para poder reemplazarla en los tests por un
    | transporte falso: los tests no pueden salir a internet ni depender de que
    | ARBA esté disponible.
    |
    */

    'transport' => CurlCotTransport::class,
];
