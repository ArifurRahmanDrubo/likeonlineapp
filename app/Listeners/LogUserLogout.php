<?php

namespace App\Listeners;

use App\Models\UserLoginHistory;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogUserLogout
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
    public function handle(Logout $event)
    {
        Log::info('Logout event triggered for user ID: ' . $event->user->id);
        $user = $event->user;

        $lastLogin = UserLoginHistory::where('user_id', $user->id)
            ->where('status', 'logged_in')
            ->latest()
            ->first();

        if ($lastLogin) {
            $durationInHours = now()->diffInMinutes($lastLogin->logged_in_at) / 60;

            // Format the duration to two decimal places
            $formattedDuration = number_format($durationInHours, 2);
            $lastLogin->update([
                'status' => 'logged_out',
                'logged_out_at' => now(),
                'duration' => $formattedDuration,
            ]);
        }
    }
}
