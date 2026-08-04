<?php

namespace App\Jobs\Ai;

use App\Services\Ai\AiBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiBillingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $organizationId)
    {
    }

    public function handle(AiBillingService $service): void
    {
        $service->processOrganization($this->organizationId);
    }
}
