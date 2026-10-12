<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales de PLATAFORMA (DMIT)
    |--------------------------------------------------------------------------
    |
    | Son las de la aplicación de Mercado Pago de DMIT (la cuenta dueña del
    | sistema) y se usan para:
    |   - el flujo OAuth con el que cada empresa conecta SU cuenta,
    |   - cobrar las SUSCRIPCIONES de los comercios.
    |
    | OJO: nunca se usan para cobrarle a los clientes de un comercio. Ese cobro
    | va con el token OAuth de la empresa (mercado_pago_accounts).
    |
    */
    'app_id' => env('MERCADOPAGO_APP_ID'),

    'client_id' => env('MERCADOPAGO_CLIENT_ID', env('MERCADOPAGO_APP_ID')),

    'client_secret' => env('MERCADOPAGO_CLIENT_SECRET'),

    /*
    | Credenciales de plataforma para cobrar la suscripción de DMIT.
    | Si están vacías, el módulo de suscripciones queda deshabilitado y los
    | cobros por link de los comercios siguen funcionando igual.
    */
    'platform_access_token' => env('MERCADOPAGO_PLATFORM_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | `webhook_secret` es la clave secreta de la aplicación (Panel de MP →
    | Tus integraciones → Webhooks). Con ella se valida el header x-signature.
    | Si está vacía, las notificaciones se RECHAZAN: mejor perder un webhook
    | (la conciliación lo recupera) que aceptar uno falsificado.
    |
    */
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),

    /*
    | URL pública a la que Mercado Pago notifica. Tiene que ser alcanzable
    | desde internet: en local no funciona, se prueba con ngrok o similar.
    */
    'webhook_url' => env('MERCADOPAGO_WEBHOOK_URL', env('APP_URL') . '/api/mercado-pago/webhook'),

    /*
    | A dónde vuelve el comprador después de pagar (nuestra app). Si es HTTPS,
    | además se activa auto_return: MP vuelve solo apenas aprueba el pago.
    */
    'back_url' => env('MERCADOPAGO_BACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | OAuth de cada empresa
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'authorize_url' => 'https://auth.mercadopago.com.ar/authorization',
        'token_url' => 'https://api.mercadopago.com/oauth/token',
        // A dónde vuelve el navegador después de autorizar. Tiene que estar
        // registrada en la aplicación de MP, si no MP rechaza la conexión.
        'redirect_url' => env('MERCADOPAGO_REDIRECT_URL', env('APP_URL') . '/api/mercado-pago/callback'),
        'scopes' => ['offline_access', 'read', 'write'],
    ],

    'api' => [
        'base_url' => 'https://api.mercadopago.com',
        'timeout' => (int) env('MERCADOPAGO_TIMEOUT', 20),
    ],

    /*
    | Minutos de margen con los que se renueva el token antes de que venza.
    */
    'token_refresh_margin_minutes' => 30,

    /*
    | Cuántos cobros pendientes reconsulta el comando de conciliación por corrida.
    */
    'reconciliation_limit' => (int) env('MERCADOPAGO_RECONCILIATION_LIMIT', 50),

];
