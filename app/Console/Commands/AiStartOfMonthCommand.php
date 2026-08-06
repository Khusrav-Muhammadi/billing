<?php

namespace App\Console\Commands;

use App\Services\Ai\AiMonthlyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AiStartOfMonthCommand extends Command
{
    protected $signature = 'app:ai-start-of-month';

    protected $description = 'Cover debt and grant monthly AI limit at start of month (Asia/Dushanbe).';

    public function handle(AiMonthlyService $service): int
    {
        if (! Carbon::now('Asia/Dushanbe')->isFirstOfMonth()) {
            $this->warn('Not the first day of month. Skipping.');
            return self::SUCCESS;
        }

        $this->info('Running start-of-month AI billing...');
        $service->processStartOfMonth();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
