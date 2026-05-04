<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\IntegrationActionLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Client $client)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
       $url = 'https://shamcrm.com/api/createSubdomain';
       $payload = [
           'subdomain' => $this->client->sub_domain,
           'country' => $this->client->country_id == 2 ? 'uz' : 'tj'
       ];

       $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $payload);

        $organizations = $this->client->organizations;
        if ($organizations->isEmpty()) {
            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: null,
                clientId: (int)$this->client->id,
                action: 'create_subdomain',
                method: 'POST',
                url: $url,
                payload: $payload,
                response: $response
            );
            return;
        }

        foreach ($organizations as $organization) {
            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: (int)$organization->id,
                clientId: (int)$this->client->id,
                action: 'create_subdomain',
                method: 'POST',
                url: $url,
                payload: $payload,
                response: $response
            );
        }
    }
}
