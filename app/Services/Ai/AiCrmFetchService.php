<?php

namespace App\Services\Ai;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiUsageRawLog;
use App\Services\IntegrationActionLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiCrmFetchService
{
    public function __construct(
        private readonly IntegrationActionLogService $logService
    ) {
    }

    /**
     * Fetch token-usage logs from CRM for all active subscriptions.
     */
    public function fetchAll(): void
    {
        $subscriptions = AiSubscription::query()
            ->with(['organization.client'])
            ->active()
            ->where('expires_at', '>=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->fetchForSubscription($subscription);
            } catch (\Throwable $e) {
                Log::error('AiCrmFetchService: failed to fetch for subscription', [
                    'subscription_id' => $subscription->id,
                    'organization_id' => $subscription->organization_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function fetchForSubscription(AiSubscription $subscription): void
    {
        $organization = $subscription->organization;
        $client = $organization->client;

        if (! $client || ! $client->sub_domain) {
            return;
        }

        $domain = config('services.sham.domain');
        $url = "https://{$client->sub_domain}-back.{$domain}/api/ai/token-logs";

        $params = [
            'b_organization_id' => (int) $organization->id,
            'limit' => 1000,
        ];
        if ($subscription->last_crm_fetch_at) {
            $params['since'] = $subscription->last_crm_fetch_at->toIso8601String();
        }

        $allLogs = [];
        $afterId = null;
        $pages = 0;

        // Пагинация по after_id на случай большого объёма.
        do {
            $pages++;
            if ($afterId) {
                $params['after_id'] = $afterId;
            }

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                ])->get($url, $params);
            } catch (\Throwable $e) {
                $this->logService->logApiResponse(
                    organizationId: (int) $organization->id,
                    clientId: (int) $client->id,
                    action: 'ai_token_logs_fetch',
                    method: 'GET',
                    url: $url,
                    payload: $params,
                    error: $e->getMessage()
                );
                return;
            }

            $this->logService->logApiResponse(
                organizationId: (int) $organization->id,
                clientId: (int) $client->id,
                action: 'ai_token_logs_fetch',
                method: 'GET',
                url: $url,
                payload: $params,
                response: $response
            );

            if (! $response->successful()) {
                Log::warning('AiCrmFetchService: non-200 response', [
                    'subscription_id' => $subscription->id,
                    'status' => $response->status(),
                ]);
                return;
            }

            $logs = $this->extractLogsPayload($response->json());
            if ($logs === []) {
                break;
            }

            $allLogs = array_merge($allLogs, $logs);
            $last = end($logs);
            $afterId = (int) ($last['id'] ?? 0) ?: null;

            // Если пришло меньше limit — данных больше нет.
            if (count($logs) < (int) $params['limit'] || ! $afterId || $pages >= 50) {
                break;
            }
        } while (true);

        if ($allLogs !== []) {
            $this->persistLogs($subscription, $allLogs);
        }

        $subscription->update(['last_crm_fetch_at' => now()]);
    }

    /**
     * CRM ApiResponse wraps payload as { result: ..., errors: null }.
     * Also accept { data: ... } for compatibility.
     */
    private function extractLogsPayload(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $logs = $json['result'] ?? $json['data'] ?? $json;

        // result may itself be { data: [...] }
        if (is_array($logs) && array_key_exists('data', $logs) && is_array($logs['data'])) {
            $logs = $logs['data'];
        }

        if (! is_array($logs)) {
            return [];
        }

        // Associative single object → wrap
        if ($logs !== [] && $this->isAssoc($logs) && isset($logs['id'])) {
            return [$logs];
        }

        // List of logs
        if ($logs !== [] && $this->isAssoc($logs) && ! isset($logs[0])) {
            return [];
        }

        return array_values($logs);
    }

    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function persistLogs(AiSubscription $subscription, array $logs): void
    {
        $orgId = $subscription->organization_id;

        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }

            $crmLogId = (int) ($log['id'] ?? 0);
            if (! $crmLogId) {
                continue;
            }

            $modelName = (string) ($log['model_key'] ?? '');
            $promptTokens = (int) ($log['prompt_tokens'] ?? 0);
            $cacheHitTokens = (int) ($log['prompt_cache_hit_tokens'] ?? 0);
            $completionTokens = (int) ($log['completion_tokens'] ?? 0);
            $createdAt = $log['created_at'] ?? now()->toDateTimeString();

            // Без актуальной цены — стоп. Нельзя писать cost=0 (бесплатное использование).
            $resolved = $this->resolveTokenPricing($modelName, $createdAt);

            $inputCost = (($promptTokens + $cacheHitTokens) / 1_000_000) * (float) $resolved['price_per_1m_input'];
            $outputCost = ($completionTokens / 1_000_000) * (float) $resolved['price_per_1m_output'];
            $calculatedCost = round($inputCost + $outputCost, 6);

            try {
                AiUsageRawLog::query()->insertOrIgnore([[
                    'organization_id' => $orgId,
                    'crm_log_id' => $crmLogId,
                    'model_name' => $modelName,
                    'prompt_tokens' => $promptTokens,
                    'prompt_cache_hit_tokens' => $cacheHitTokens,
                    'completion_tokens' => $completionTokens,
                    'calculated_cost' => $calculatedCost,
                    'cost_currency_id' => $resolved['currency_id'],
                    'price_per_1m_input_snapshot' => $resolved['price_per_1m_input'],
                    'price_per_1m_output_snapshot' => $resolved['price_per_1m_output'],
                    'margin_percent_snapshot' => $resolved['margin_percent'],
                    'processed' => false,
                    'created_at' => $createdAt,
                    'fetched_at' => now(),
                ]]);
            } catch (\Throwable $e) {
                Log::error('AiCrmFetchService: failed to insert raw log', [
                    'organization_id' => $orgId,
                    'crm_log_id' => $crmLogId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }

    /**
     * Актуальная цена токенов на дату лога из ai_model_prices.
     * Без цены — RuntimeException (никаких fallback / 0).
     * Актуальный прайс только из ai_model_prices.
     *
     * @return array{price_per_1m_input: float, price_per_1m_output: float, margin_percent: float, currency_id: int}
     */
    private function resolveTokenPricing(string $modelName, string $at): array
    {
        $modelName = trim($modelName);
        if ($modelName === '') {
            throw new RuntimeException('AI usage log has empty model_key; cannot price tokens.');
        }

        $onDate = Carbon::parse($at)->toDateString();

        $aiModel = AiModel::query()
            ->where('name', $modelName)
            ->where('is_active', true)
            ->first();

        if (! $aiModel) {
            throw new RuntimeException(
                "AI model [{$modelName}] not found or inactive; cannot price tokens."
            );
        }

        $price = $aiModel->resolvePriceAt($onDate);
        if (! $price || (int) $price->currency_id <= 0) {
            throw new RuntimeException(
                "AI model [{$modelName}] has no price for date {$onDate}; cannot price tokens."
            );
        }

        // Списание по продажной цене (cost / (1 - margin/100)), уже в price_per_1m_*.
        return [
            'price_per_1m_input' => (float) $price->price_per_1m_input,
            'price_per_1m_output' => (float) $price->price_per_1m_output,
            'margin_percent' => (float) $price->margin_percent,
            'currency_id' => (int) $price->currency_id,
        ];
    }
}
