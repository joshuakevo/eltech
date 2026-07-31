<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Post savings interest at midnight on the 1st of every month -- flat-rate
        // products only. Tiered-interest products accrue silently and are credited
        // only when staff manually run "Post Interest" (typically once a year).
        $schedule->command('eltech:post-interest --method=flat')->monthlyOn(1, '00:00');

        // Accrue fixed deposit interest expense at 00:15 on the 1st of every month
        $schedule->command('eltech:accrue-fd-interest')->monthlyOn(1, '00:15');

        // Backstop for mobile money deposits/withdrawals stuck pending/processing --
        // covers cases where MarzPay's webhook is delayed or unreachable and nobody
        // has the page open to trigger the live client/admin polling.
        $schedule->command('eltech:reconcile-mobile-money')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
