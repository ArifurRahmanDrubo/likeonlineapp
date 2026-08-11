<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Broadcasting is intentionally DISABLED on the Laravel side. All real-time
    | traffic data is pushed by the Node.js monitoring-service (monitoring-service)
    | directly to the Vue frontend over a public Pusher channel (traffic.{key}).
    | Laravel only resolves the customer + MikroTik server and calls
    | POST /monitor/start on the Node service — no broadcasting logic here.
    |
    */

    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [

        'null' => [
            'driver' => 'null',
        ],

    ],

];
