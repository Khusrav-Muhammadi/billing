<?php

namespace App\Jobs\Ai;

use App\Models\Organization;
use App\Services\IntegrationActionLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentToggleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly bool $enabled
    ) {
    }

    public function handle(): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if (! $organization) {
            Log::warning('AiAgentToggleJob: organization not found', ['id' => $this->organizationId]);
            return;
        }

        $client = $organization->client;
        $domain = config('services.sham.domain');
        $url = "https://{$client->sub_domain}-back.{$domain}/api/ai/agent-toggle";

        $payload = [
            'enabled' => $this->enabled,
            'b_organization_id' => $this->organizationId,
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($url, $payload);
        } catch (\Throwable $e) {
            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: $this->organizationId,
                clientId: (int) ($client->id ?? 0),
                action: 'ai_agent_toggle',
                method: 'POST',
                url: $url,
                payload: $payload,
                error: $e->getMessage()
            );

            return;
        }

        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: $this->organizationId,
            clientId: (int) ($client->id ?? 0),
            action: 'ai_agent_toggle',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response
        );
    }
}
