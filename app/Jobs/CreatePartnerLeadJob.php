<?php

namespace App\Jobs;

use App\Models\DemoRequest;
use App\Services\PartnerProgram\PartnerLeadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Заявка на демо с сайта -> тёплый лид первому партнёру в очереди. */
class CreatePartnerLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public DemoRequest $demoRequest)
    {
    }

    public function handle(PartnerLeadService $leads): void
    {
        $leads->fromDemoRequest($this->demoRequest->fresh());
    }
}
