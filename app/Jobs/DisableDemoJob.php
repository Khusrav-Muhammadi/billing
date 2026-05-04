<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\IntegrationActionLogService;
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
        $domain = config('services.sham.domain');
        $url = "https://{$this->domain}-back.{$domain}/api/organization/disable-demo";

        $organizations = $this->client->organizations()->get();
        foreach ($organizations as $organization) {

            $payload = [
                'b_organization_id' => $organization->id,
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($url, $payload);

            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: (int)$organization->id,
                clientId: (int)$this->client->id,
                action: 'disable_demo',
                method: 'POST',
                url: $url,
                payload: $payload,
                response: $response
            );
        }
    }
}
