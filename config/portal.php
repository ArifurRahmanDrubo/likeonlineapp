<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Customer Portal Frontend
    |--------------------------------------------------------------------------
    |
    | The customer portal SPA is served separately from this Laravel backend.
    | Payment gateway callbacks are plain browser redirects, so after a
    | successful bKash/Nagad settlement the customer is sent back to this
    | origin (e.g. https://portal.example.com). Defaults to APP_URL when
    | PORTAL_FRONTEND_URL is not set.
    |
    */
    'frontend_url' => rtrim((string) env('PORTAL_FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),
];
