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

class SyncCallAnalyseQuotaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function __construct(
        public readonly int $organizationId,
        public readonly int $dailyMinutes,
        public readonly ?int $planId,
        public readonly ?string $expiresAt,
    ) {
    }

    public function handle(): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if (! $organization) {
            throw new RuntimeException(
                "SyncCallAnalyseQuotaJob: organization #{$this->organizationId} not found."
            );
        }

        $client = $organization->client;
        if (! $client || ! $client->sub_domain) {
            throw new RuntimeException(
                "SyncCallAnalyseQuotaJob: client/sub_domain missing for organization #{$this->organizationId}."
            );
        }

        $domain = config('services.sham.domain');
        if (! is_string($domain) || trim($domain) === '') {
            throw new RuntimeException('SyncCallAnalyseQuotaJob: services.sham.domain is not configured.');
        }

        // Тот же хост, что и у лимита пользователей: тенант CRM.
        $url = "https://{$client->sub_domain}-back.{$domain}/api/ai/call-analyse-quota";

        $payload = [
            'b_organization_id' => $this->organizationId,
            'daily_minutes' => $this->dailyMinutes,
            'plan_id' => $this->planId,
            'expires_at' => $this->expiresAt,
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->timeout(20)->post($url, $payload);
        } catch (\Throwable $e) {
            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: $this->organizationId,
                clientId: (int) $client->id,
                action: 'call_analyse_quota_sync',
                method: 'POST',
                url: $url,
                payload: $payload,
                error: $e->getMessage()
            );

            throw new RuntimeException(
                "SyncCallAnalyseQuotaJob: CRM quota sync failed for org #{$this->organizationId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: $this->organizationId,
            clientId: (int) $client->id,
            action: 'call_analyse_quota_sync',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                "SyncCallAnalyseQuotaJob: CRM quota sync HTTP {$response->status()} for org #{$this->organizationId}: {$response->body()}"
            );
        }
    }
}
