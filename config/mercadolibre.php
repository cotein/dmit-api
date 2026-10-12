<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credenciales de la aplicación de DMIT en el DevCenter de MercadoLibre
    |--------------------------------------------------------------------------
    |
    | Son las de la aplicación de DMIT (la cuenta dueña del sistema). Con ellas
    | cada empresa conecta SU cuenta de vendedor por OAuth: el `app_id` y el
    | `client_secret` identifican a la aplicación, no al vendedor.
    |
    | OJO: las ventas y las publicaciones SIEMPRE se leen y escriben con el
    | access token de la empresa (mercadolibre_accounts), nunca con estas.
    |
    */
    'app_id' => env('MERCADOLIBRE_APP_ID'),

    'client_secret' => env('MERCADOLIBRE_CLIENT_SECRET'),

    'oauth' => [

        /*
        | El authorization_endpoint cambia según el país del vendedor
        | (auth.mercadolibre.com.ar, .com.mx, etc.). Por defecto Argentina.
        */
        'authorize_url' => env('MERCADOLIBRE_AUTHORIZE_URL', 'https://auth.mercadolibre.com.ar/authorization'),

        'token_url' => env('MERCADOLIBRE_TOKEN_URL', 'https://api.mercadolibre.com/oauth/token'),

        /*
        | Tiene que ser EXACTAMENTE la URL registrada en el DevCenter.
        | MercadoLibre no admite parámetros variables: para eso viaja el `state`.
        */
        'redirect_url' => env('MERCADOLIBRE_REDIRECT_URL'),

        /*
        | PKCE: si la aplicación lo tiene habilitado en el DevCenter, mandar
        | `code_challenge` es obligatorio. El `code_verifier` nunca viaja al
        | navegador: se guarda del lado del servidor atado al `state`.
        */
        'pkce' => env('MERCADOLIBRE_PKCE', true),

        /*
        | Minutos de vida del `state` firmado. Es la ventana que tiene el dueño
        | de la empresa para autorizar en MercadoLibre.
        */
        'state_ttl_minutes' => 10,
    ],

    'api' => [
        'base_url' => env('MERCADOLIBRE_API_URL', 'https://api.mercadolibre.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vuelta al frontend
    |--------------------------------------------------------------------------
    |
    | A dónde se manda al usuario cuando vuelve de autorizar en MercadoLibre.
    | El callback es una carga completa de página: sin esto el usuario termina
    | mirando una respuesta JSON de la API.
    |
    */
    'back_url' => env('MERCADOLIBRE_BACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    |
    | El access token vive 6 h y el refresh token 6 meses, pero el refresh es de
    | UN SOLO USO y rota: sólo vale el último que devolvió MercadoLibre. Se
    | renueva con margen (antes de que venza la llamada que importa) y siempre
    | con la fila de la cuenta bloqueada, para que dos procesos no quemen la
    | cadena al mismo tiempo.
    |
    */
    'token_refresh_margin_minutes' => env('MERCADOLIBRE_REFRESH_MARGIN', 60),

    /*
    |--------------------------------------------------------------------------
    | Precios
    |--------------------------------------------------------------------------
    |
    | De qué lista de precios sale el precio que se manda a MercadoLibre. Si no
    | se configura ninguna, se usa la primera lista de la empresa (la de menor
    | id). El stock, en cambio, sale siempre del historial de stock del producto.
    |
    */
    'pricing' => [
        'price_list_id' => env('MERCADOLIBRE_PRICE_LIST_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronización
    |--------------------------------------------------------------------------
    |
    | Los topes son de MercadoLibre, no nuestros:
    |   - items/search devuelve 50 por defecto y 100 como máximo,
    |   - /items/bulk acepta 20 ids por llamada,
    |   - el `scroll_id` de search_type=scan vence a los 5 minutos.
    |
    */
    'sync' => [
        'bulk_chunk' => 20,
        'page_size' => 100,
        'scroll_ttl_minutes' => 5,
        'max_retries' => 4,
        'backoff_base_ms' => 500,
        'orders_page_size' => 50,
        'orders_days_back' => env('MERCADOLIBRE_ORDERS_DAYS_BACK', 30),
    ],
];
