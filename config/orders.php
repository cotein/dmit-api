<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pedidos
    |--------------------------------------------------------------------------
    |
    | strict_totals:
    |   false (default) -> el pedido se recalcula en el servidor y, si el total que
    |                      mandó el cliente no coincide, se registra un warning.
    |                      Es la fase de observación para detectar si el frontend
    |                      calcula distinto.
    |   true            -> además se rechaza con 422 el pedido cuyo total de ítem
    |                      no coincida con el cálculo del servidor.
    |
    */

    'strict_totals' => (bool) env('ORDERS_STRICT_TOTALS', false),

];
