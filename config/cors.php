<?php

return [

    'paths' => [
        'api/*',
        '/login',
        '/logout',
        '/register',
        '/auth/google',
        'oauth/*',
        'verify-email/*',
        '/email/resend',
        'storage/companies/*',
        'updates',
        '/forgotPassword/reset/code',
        '/forgotPassword/validate/code',
        '/forgotPassword/resetPassword',
         'sanctum/csrf-cookie'

    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:7000', 'http://localhost:5173', 'http://localhost:3000', 'http://localhost:3131', 'http://localhost:8888', 'https://www.facturador.dmit.ar', 'https://www.dmit.ar', 'https://emailsender.dmit.ar', 'https://facturador.dmit.ar'],

    'allowed_origins_patterns' => ['/^https?:\/\/(.+\.)?dmit\.ar$/', '/^http:\/\/localhost:5173$/', '/^http:\/\/localhost:7000$/', '/^https:\/\/www\.dmit\.ar$/', '/^http:\/\/localhost:8888$/', '/^https:\/\/www\.facturador\.dmit\.ar$/', '/^https:\/\/.emailsender\.dmit\.ar$/', '/^https:\/\/.facturador\.dmit\.ar$/'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
