<?php

return [
    "sandbox"         => env("BKASH_SANDBOX", true),

    "bkash_app_key"     => env("BKASH_APP_KEY", ""),
    "bkash_app_secret" => env("BKASH_APP_SECRET", ""),
    "bkash_username"      => env("BKASH_USERNAME", ""),
    "bkash_password"     => env("BKASH_PASSWORD", ""),

    "bkash_app_key_2"     => env("BKASH_APP_KEY_2", ""),
    "bkash_app_secret_2" => env("BKASH_APP_SECRET_2", ""),
    "bkash_username_2"      => env("BKASH_USERNAME_2", ""),
    "bkash_password_2"     => env("BKASH_PASSWORD_2", ""),

    "bkash_app_key_3"     => env("BKASH_APP_KEY_3", ""),
    "bkash_app_secret_3" => env("BKASH_APP_SECRET_3", ""),
    "bkash_username_3"      => env("BKASH_USERNAME_3", ""),
    "bkash_password_3"     => env("BKASH_PASSWORD_3", ""),

    "bkash_app_key_4"     => env("BKASH_APP_KEY_4", ""),
    "bkash_app_secret_4" => env("BKASH_APP_SECRET_4", ""),
    "bkash_username_4"      => env("BKASH_USERNAME_4", ""),
    "bkash_password_4"     => env("BKASH_PASSWORD_4", ""),

    // Points at this app's dedicated settlement callback (routes/web.php).
    // A relative path is resolved to an absolute URL at request time by the
    // payment controller; override via BKASH_CALLBACK_URL in .env if needed.
    "callbackURL"     => env("BKASH_CALLBACK_URL", "/portal/payments/bkash/callback"),
    'timezone'        => 'Asia/Dhaka',
];
