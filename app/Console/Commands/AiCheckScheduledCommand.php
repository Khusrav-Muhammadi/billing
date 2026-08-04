<?php

namespace App\Console\Commands;

use App\Services\Ai\AiScheduledActivationService;
use Illuminate\Console\Command;

class AiCheckScheduledCommand extends Command
{
    protected $signature = 'app:ai-check-scheduled';

    protected $description = 'Check and activate AI agents with scheduled activation date due today.';

    public function handle(AiScheduledActivationService $service): int
    {
        $this->info('Checking scheduled AI agent activations...');
        $service->checkAndActivate();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
