<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\IntegrationActionLogService;
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
        $domain = config('services.sham.domain');
        $url = "https://{$client->sub_domain}-back.{$domain}/api/organization/tariff-extension";

        $payload = [
            'has_access' => $this->has_access,
            'b_organization_id' => $this->organization->id,
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $payload);

        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: (int)$this->organization->id,
            clientId: (int)($client->id ?? 0),
            action: 'tariff_extension',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response
        );
    }
}
