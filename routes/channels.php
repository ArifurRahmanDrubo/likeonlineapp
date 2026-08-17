<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| The live-traffic pipeline in this project uses PUBLIC `traffic.*` channels
| (the Node.js monitoring-service broadcasts directly, so no Laravel auth is
| involved). The private `customer-traffic.{username}` channel below is
| defined so the client role can be authorized to subscribe to per-customer
| traffic when private channels are enabled.
|
*/

Broadcast::channel('customer-traffic.{username}', function ($user, $username) {
    // Only the linked ISP customer may listen to their own traffic channel.
    return $user
        && $user->customer
        && $user->customer->username === $username;
});
