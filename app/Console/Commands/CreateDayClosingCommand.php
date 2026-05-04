<?php

namespace App\Console\Commands;

use App\Services\DayClosings\DayClosingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateDayClosingCommand extends Command
{
    protected $signature = 'app:create-day-closing';

    protected $description = 'Creates a day closing document for the current day.';

    public function handle(DayClosingService $dayClosingService): int
    {
        $date =  now()->startOfDay();

        $dayClosingService->createDocumentsForPeriod(
            $date->copy(),
            $date->copy(),
            1
        );

        return self::SUCCESS;
    }
}
