<?php

namespace App\Jobs\Storage;

use App\Support\CrmHttp;

use App\Models\Organization;
use App\Services\IntegrationActionLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Отправляет в CRM итоговый лимит файлового хранилища (GB).
 *
 * Значение абсолютное, не прирост — в отличие от add-pack.
 * Так истёкшие пакеты уменьшают лимит, а продление не удваивает его.
 * Схема та же, что у SyncCallAnalyseQuotaJob.
 */
class SyncStorageQuotaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function __construct(
        public readonly int $organizationId,
        public readonly int $storageLimitGb,
    ) {
    }

    public function handle(): void
    {
        $organization = Organization::query()->find($this->organizationId);
        if (! $organization) {
            throw new RuntimeException("SyncStorageQuotaJob: organization #{$this->organizationId} not found.");
        }

        $client = $organization->client;
        if (! $client || ! $client->sub_domain) {
            throw new RuntimeException("SyncStorageQuotaJob: client/sub_domain missing for organization #{$this->organizationId}.");
        }

        $domain = config('services.sham.domain');
        if (! is_string($domain) || trim($domain) === '') {
            throw new RuntimeException('SyncStorageQuotaJob: services.sham.domain is not configured.');
        }

        $url = "https://{$client->sub_domain}-back.{$domain}/api/organization/storage-quota";

        $payload = [
            'b_organization_id' => $this->organizationId,
            'storage_limit_gb' => $this->storageLimitGb,
        ];

        try {
            $response = CrmHttp::client()->withHeaders(['Accept' => 'application/json'])
                ->timeout(20)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            $this->log($client->id, $url, $payload, error: $e->getMessage());

            throw new RuntimeException(
                "SyncStorageQuotaJob: CRM sync failed for org #{$this->organizationId}: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->log($client->id, $url, $payload, response: $response);

        if (! $response->successful()) {
            throw new RuntimeException(
                "SyncStorageQuotaJob: CRM HTTP {$response->status()} for org #{$this->organizationId}: {$response->body()}"
            );
        }
    }

    private function log(int $clientId, string $url, array $payload, $response = null, ?string $error = null): void
    {
        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: $this->organizationId,
            clientId: $clientId,
            action: 'storage_quota_sync',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response,
            error: $error
        );
    }
}
