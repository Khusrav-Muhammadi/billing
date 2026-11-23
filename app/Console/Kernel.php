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

        // app:control-nfr-command удалена в fb59706 (4 мая 2026): NFR — рабочая CRM партнёра и не отключается.
        // Строка расписания осталась и каждую ночь падала с «command not defined».
        // Тёплые лиды партнёров: SLA 24 часа (команда ничего не делает, пока PARTNERS_LEADS_ENABLED=false).
        $schedule->command('partners:leads-sla')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('app:notify-client')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('app:mark-unpartnered-organizations-without-connection')->dailyAt('00:05')->withoutOverlapping();
        $schedule->command('app:create-day-closing')->dailyAt('23:50')->withoutOverlapping();

 
      $schedule->command('app:ai-fetch-and-bill')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('app:ai-end-of-month')->dailyAt('23:59')->withoutOverlapping();
        $schedule->command('app:ai-start-of-month')->dailyAt('00:01')->withoutOverlapping();
        $schedule->command('app:ai-check-scheduled')->dailyAt('09:00')->withoutOverlapping();
        // Просроченный тариф анализа должен обнулить минуты в CRM.
        $schedule->command('app:ai-sync-call-analyse-quotas --queued')->dailyAt('00:10')->withoutOverlapping();
        // Лимит файлового хранилища в CRM: тариф + активные пакеты add_storage.
        $schedule->command('app:sync-storage-quotas --queued')->dailyAt('00:15')->withoutOverlapping();
        // Индекс здоровья клиента (светофор) за вчера: после закрытия дня (23:50) и ночных синхронизаций.
        $schedule->command('app:client-health-calculate')->dailyAt('01:00')->withoutOverlapping(180)->runInBackground();
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
