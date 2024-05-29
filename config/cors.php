<?php

return [

    'paths' => [
        'api/*',
        '/login',
        '/logout',
        '/register',
        'oauth/*'
    ],

    'allowed_methods' => ['*'],

    //'allowed_origins' => [env('CORS_ALLOW_ORIGIN')],

    //'allowed_origins_patterns' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => ['/^https?:\/\/(.+\.)?dmit\.ar$/', '/^http:\/\/localhost:5173$/', '/^https:\/\/www\.dmit\.ar$/'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
