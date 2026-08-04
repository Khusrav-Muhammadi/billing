<?php

namespace App\Services\Ai;

use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiTokenPricing;
use App\Models\Ai\AiUsageRawLog;
use App\Services\IntegrationActionLogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $params = [];
        if ($subscription->last_crm_fetch_at) {
            $params['since'] = $subscription->last_crm_fetch_at->toIso8601String();
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

        $logs = $response->json('data') ?? $response->json() ?? [];

        if (! is_array($logs)) {
            return;
        }

        $this->persistLogs($subscription, $logs);

        $subscription->update(['last_crm_fetch_at' => now()]);
    }

    private function persistLogs(AiSubscription $subscription, array $logs): void
    {
        $orgId = $subscription->organization_id;

        foreach ($logs as $log) {
            $crmLogId = (int) ($log['id'] ?? 0);
            if (! $crmLogId) {
                continue;
            }

            $modelName = (string) ($log['model_key'] ?? '');
            $promptTokens = (int) ($log['prompt_tokens'] ?? 0);
            $cacheHitTokens = (int) ($log['prompt_cache_hit_tokens'] ?? 0);
            $completionTokens = (int) ($log['completion_tokens'] ?? 0);
            $createdAt = $log['created_at'] ?? now()->toDateTimeString();

            $pricing = AiTokenPricing::query()
                ->current($modelName)
                ->where('is_active', true)
                ->first();

            $calculatedCost = 0.0;
            $costCurrencyId = null;
            $inputSnapshot = null;
            $outputSnapshot = null;
            $marginSnapshot = null;

            if ($pricing) {
                $inputCost = (($promptTokens + $cacheHitTokens) / 1_000_000) * (float) $pricing->price_per_1m_input;
                $outputCost = ($completionTokens / 1_000_000) * (float) $pricing->price_per_1m_output;
                $calculatedCost = round($inputCost + $outputCost, 6);
                $costCurrencyId = $pricing->price_currency_id;
                $inputSnapshot = $pricing->price_per_1m_input;
                $outputSnapshot = $pricing->price_per_1m_output;
                $marginSnapshot = $pricing->margin_percent;
            } else {
                Log::warning('AiCrmFetchService: no pricing found for model', [
                    'model_name' => $modelName,
                    'organization_id' => $orgId,
                ]);
            }

            try {
                AiUsageRawLog::query()->insertOrIgnore([[
                    'organization_id' => $orgId,
                    'crm_log_id' => $crmLogId,
                    'model_name' => $modelName,
                    'prompt_tokens' => $promptTokens,
                    'prompt_cache_hit_tokens' => $cacheHitTokens,
                    'completion_tokens' => $completionTokens,
                    'calculated_cost' => $calculatedCost,
                    'cost_currency_id' => $costCurrencyId,
                    'price_per_1m_input_snapshot' => $inputSnapshot,
                    'price_per_1m_output_snapshot' => $outputSnapshot,
                    'margin_percent_snapshot' => $marginSnapshot,
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
            }
        }
    }
}
