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
            ->dailyAt('3:30')
            ->withoutOverlapping()
            ->onOneServer();

        // Schedule payroll generation
        $schedule->command('payroll:generate')
            ->dailyAt('3:20')
            ->withoutOverlapping()
            ->onOneServer();

        // Schedule disabling expired customers
        $schedule->command('customers:disable-expired')
            ->dailyAt('23:37') // 'daily()' এর বদলে 'dailyAt()' ব্যবহার করা ভালো
            ->withoutOverlapping()
            ->onOneServer();

        // Process due scheduled package/status changes once a day
        $schedule->command('isp:process-scheduled-changes')
            ->dailyAt('2:27')
            ->withoutOverlapping()
            ->onOneServer();

        // Retry pending MikroTik re-enables for paid customers every hour
        $schedule->command('mikrotik:sync-pending')
            ->hourlyAt(2)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('mikrotik:sync-m-users')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // --- নতুন ব্যাকআপ কমান্ডসমূহ ---

        // পুরাতন ব্যাকআপ ডিলিট করার জন্য (প্রতিদিন রাত ৩টায়)
        $schedule->command('backup:clean')
            ->dailyAt('03:02');

        // নতুন ব্যাকআপ তৈরি করার জন্য (প্রতিদিন ভোর ৪টায়)
        $schedule->command('backup:run')
            ->dailyAt('04:02');
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