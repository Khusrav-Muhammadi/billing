<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:control-demo-command')->dailyAt('00:01');
        $schedule->command('send:email')->dailyAt('00:01');


        $schedule->command('app:control-nfr-command')->dailyAt('00:01');
        $schedule->command('app:notify-client')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('app:mark-unpartnered-organizations-without-connection')->dailyAt('00:05')->withoutOverlapping();
        $schedule->command('app:create-day-closing')->dailyAt('23:50')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
