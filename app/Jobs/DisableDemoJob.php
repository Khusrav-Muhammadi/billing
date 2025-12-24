<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DisableDemoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Client $client, public string $domain)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = env('APP_DOMAIN');
        $url = "https://{$this->domain}-back.{$domain}/api/organization/disable-demo";

        $organizations = $this->client->organizations()->get();
        foreach ($organizations as $organization) {

            Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($url, [
                'b_organization_id' => $organization->id,
            ]);
        }
    }
}
