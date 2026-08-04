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
use RuntimeException;

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
            throw new RuntimeException(
                "AiAgentToggleJob: organization #{$this->organizationId} not found."
            );
        }

        $client = $organization->client;
        if (! $client || ! $client->sub_domain) {
            throw new RuntimeException(
                "AiAgentToggleJob: client/sub_domain missing for organization #{$this->organizationId}."
            );
        }

        $domain = config('services.sham.domain');
        if (! is_string($domain) || trim($domain) === '') {
            throw new RuntimeException('AiAgentToggleJob: services.sham.domain is not configured.');
        }

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
                clientId: (int) $client->id,
                action: 'ai_agent_toggle',
                method: 'POST',
                url: $url,
                payload: $payload,
                error: $e->getMessage()
            );

            throw new RuntimeException(
                "AiAgentToggleJob: CRM toggle failed for org #{$this->organizationId} (enabled=" . ($this->enabled ? '1' : '0') . "): {$e->getMessage()}",
                0,
                $e
            );
        }

        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: $this->organizationId,
            clientId: (int) $client->id,
            action: 'ai_agent_toggle',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                "AiAgentToggleJob: CRM toggle HTTP {$response->status()} for org #{$this->organizationId} (enabled=" . ($this->enabled ? '1' : '0') . "): {$response->body()}"
            );
        }
    }
}
