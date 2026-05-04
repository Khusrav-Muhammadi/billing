<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeleteClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $domain)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $domain = config('services.sham.domain');
        $url = 'https://' . $domain . '/api/tenant/delete';

        $data = [
            'subdomain' => $this->domain,
        ];

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->delete($url, $data);

    }
}
