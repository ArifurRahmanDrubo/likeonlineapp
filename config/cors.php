<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'https://drubocodelab.xyz',
        'https://app.likeonlinebd.com',
        'https://likeonlinebd.com'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
// return [

//     'paths' => ['api/*', 'sanctum/csrf-cookie', 'start-monitoring'], // আপনার API রুটগুলো

//     'allowed_methods' => ['*'],

//     'allowed_origins' => ['*'], // অথবা নির্দিষ্ট ফ্রন্টএন্ড URL যেমন ['http://localhost:5173']

//     'allowed_origins_patterns' => [],

//     'allowed_headers' => ['*'],

//     'exposed_headers' => [],

//     'max_age' => 0,

//     'supports_credentials' => true, // credentials বা auth token পাঠালে এটি true রাখতে হবে

// ];