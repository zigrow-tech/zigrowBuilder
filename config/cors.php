<?php




return [
    'paths' => ['*','api/*','import-template', 'sanctum/csrf-cookie','leads','marketing-leads', 'login','template/*', 'logout', 'profile', 'profile/*','profile/update','user','initiate-payment','register', 'check-email','forgot-password','reset-password','leads/disabled','user/*'],

    'allowed_methods' => ['*'],

   




// 'allowed_origins' => [],

//     'allowed_origins_patterns' => ['/^https:\/\/([a-z0-9-]+\.)?zigrow\.com$/'],

 'allowed_origins' => [
        env('FRONTEND_URL'),
        env('APP_URL'),
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:8000',
        'http://127.0.0.1',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [
        '/^https:\/\/([a-z0-9-]+\.)?zigrow\.com$/',
        '/^http:\/\/localhost(:[0-9]+)?$/',
        '/^http:\/\/127\.0\.0\.1(:[0-9]+)?$/',
    ],



    'allowed_headers' => [
    'Content-Type',
    'X-Requested-With',
    'X-CSRF-TOKEN',
    'X-XSRF-TOKEN',
    'Accept',
    'Authorization',
],


    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // 👈 VERY IMPORTANT
];

