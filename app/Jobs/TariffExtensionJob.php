<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class TariffExtensionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Organization $organization, public ?bool $has_access = true)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = $this->organization->client;
        $domain = env('APP_DOMAIN');
        $url = "https://{$client->sub_domain}-back.{$domain}/api/organization/tariff-extension";

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, [
            'has_access' => $this->has_access,
            'b_organization_id' => $this->organization->id,
        ]);
    }
}
