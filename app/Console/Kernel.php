<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\ListenTgMessage::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->command('InformCustom')->everyMinute();
        // $schedule->command('CanUpRecheck')->everyMinute();
        // $schedule->command('ServerScheduleGenerate')->everyMinute();
        // $schedule->command('DeleteOldData')->dailyAt('06:55');
        // $schedule->command('ServerScheduleGenerate')->dailyAt('07:00');
        $schedule->command('checkInWork')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        Commands\CustomArrive::class;

        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
