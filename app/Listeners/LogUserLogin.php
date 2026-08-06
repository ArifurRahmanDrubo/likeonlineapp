<?php

namespace App\Listeners;

use App\Models\UserLoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Stevebauman\Location\Facades\Location;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogUserLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event)
    {
        Log::info('Login event triggered for user ID: ' . $event->user->id);
        $user = $event->user;
        $ip = request()->ip();
        // $ip = '103.76.195.2';
        // Use stevebauman/location to get location data
        $location = Location::get();


        UserLoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'country' => $location->countryName ?? 'N/A',
            'region' => $location->regionName ?? 'N/A',
            'city' => $location->cityName ?? 'N/A',
            'zip' => $location->zipCode ?? 'N/A',
            'organization' => $location->organization ?? 'N/A', // If available
            'status' => 'logged_in',
            'logged_in_at' => now(),
        ]);
    }
}
