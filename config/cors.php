<?php

return [

    'paths' => [
        'api/*',
        '/login',
        '/logout',
        '/register',
        '/auth/google',
        'oauth/*',
        'email/verify/*',
        'email/resend',
        'storage/companies/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('CORS_ALLOW_ORIGIN'), 'http://localhost:5173', 'http://localhost:8888', 'https://www.facturador.dmit.ar', 'https://www.dmit.ar', 'https://emailsender.dmit.ar'],

    'allowed_origins_patterns' => ['/^https?:\/\/(.+\.)?dmit\.ar$/', '/^http:\/\/localhost:5173$/', '/^https:\/\/www\.dmit\.ar$/', '/^http:\/\/localhost:8888$/', '/^https:\/\/www\.facturador\.dmit\.ar$/', '/^https:\/\/.emailsender\.dmit\.ar$/'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
