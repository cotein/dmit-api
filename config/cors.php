<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        '*'
        /* 'api/*',
        '/login',
        '/logout', */
    ],

    'allowed_methods' => ['POST', 'PUT', 'PATCH', 'GET', 'DELETE', 'OPTIONS'],

    'allowed_origins' => ['http://localhost:9000', 'http://localhost:5173'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Access-Control-Allow-Headers',
        'Origin',
        'crossDomain',
        'Accept',
        'X-Requested-With',
        'Authorization',
        'Content-Type',
        'Access-Control-Request-Method',
        'Access-Control-Request-Headers',
        'Access-Control-Allow-Methods',
        'Access-Control-Allow-Origin'
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
