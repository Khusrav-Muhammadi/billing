<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SubDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $subDomain)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post('https://sham360.com/api/createSubdomain', [
            'subdomain' => $this->subDomain
        ]);

    }
}
