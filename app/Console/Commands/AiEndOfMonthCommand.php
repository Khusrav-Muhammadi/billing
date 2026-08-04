<?php

namespace App\Console\Commands;

use App\Services\Ai\AiMonthlyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AiEndOfMonthCommand extends Command
{
    protected $signature = 'app:ai-end-of-month';

    protected $description = 'Burn remaining limited_balance at end of month (Asia/Dushanbe).';

    public function handle(AiMonthlyService $service): int
    {
        if (! Carbon::now('Asia/Dushanbe')->isLastOfMonth()) {
            $this->warn('Not the last day of month. Skipping.');
            return self::SUCCESS;
        }

        $this->info('Running end-of-month limited balance expiration...');
        $service->processEndOfMonth();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
