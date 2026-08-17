<?php

return [
    'sandbox' => env("SSLCOMMERZ_SANDBOX", false), // For Sandbox, use "true", For Live, use "false"
    'middleware' => 'web',//you can change this middleware according to you
    'store_id' => env("SSLCOMMERZ_STORE_ID"),
    'store_password' => env("SSLCOMMERZ__STORE_PASSWORD"),
    // Point at this app's dedicated settlement callbacks (routes/web.php).
    // Relative paths are resolved to absolute URLs at request time by the
    // payment controller; override via env if needed.
    'success_url' => '/portal/payments/sslcommerz/success',
    'failed_url' => '/portal/payments/sslcommerz/fail',
    'cancel_url' => '/portal/payments/sslcommerz/cancel',
    'ipn_url' => '/portal/payments/sslcommerz/ipn',
    'return_response' => 'html', //html or json html means blade return json means json data return
];
