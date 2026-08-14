<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Schedule monthly invoice generation
        $schedule->command('generate:monthly-invoices')
            ->monthlyOn(1, '00:01')
            ->withoutOverlapping()
            ->onOneServer();

        // Schedule payroll generation
        $schedule->command('payroll:generate')
            ->monthlyOn(1, '00:01')
            ->withoutOverlapping()
            ->onOneServer();

        // Schedule disabling expired customers
        $schedule->command('customers:disable-expired')
            ->daily() // Changed to daily for clarity, adjust as needed
            ->at('00:01')
            ->withoutOverlapping()
            ->onOneServer();

        // Process due scheduled package/status changes once a day
        $schedule->command('isp:process-scheduled-changes')
            ->dailyAt('00:01')
            ->withoutOverlapping()
            ->onOneServer();

        // Retry pending MikroTik re-enables for paid customers every hour
        $schedule->command('mikrotik:sync-pending')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
