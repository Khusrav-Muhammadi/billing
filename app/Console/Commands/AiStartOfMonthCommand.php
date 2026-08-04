<?php

namespace App\Console\Commands;

use App\Services\Ai\AiMonthlyService;
use Illuminate\Console\Command;

class AiStartOfMonthCommand extends Command
{
    protected $signature = 'app:ai-start-of-month';

    protected $description = 'Cover debt and grant monthly AI limit at start of month (Asia/Dushanbe).';

    public function handle(AiMonthlyService $service): int
    {
        $this->info('Running start-of-month AI billing...');
        $service->processStartOfMonth();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
