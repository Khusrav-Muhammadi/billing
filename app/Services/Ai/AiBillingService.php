<?php

namespace App\Services\Ai;

use App\Jobs\Ai\AiAgentToggleJob;
use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiUsageLog;
use App\Models\Ai\AiUsageRawLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiBillingService
{
    public function processOrganization(int $orgId): void
    {
        DB::transaction(function () use ($orgId): void {
            $balance = AiBalance::query()
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                return;
            }

            $periodStart = now()->subMinutes(30);
            $periodEnd = now();

            $rawIds = AiUsageRawLog::query()
                ->where('organization_id', $orgId)
                ->unprocessed()
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($rawIds->isEmpty()) {
                return;
            }

            $balanceCurrencyId = (int) $balance->currency_id;

            $wrongCurrencyIds = AiUsageRawLog::query()
                ->whereIn('id', $rawIds)
                ->where(function ($q) use ($balanceCurrencyId) {
                    $q->whereNull('cost_currency_id')
                        ->orWhere('cost_currency_id', '!=', $balanceCurrencyId);
                })
                ->pluck('cost_currency_id')
                ->unique()
                ->values()
                ->all();

            if ($wrongCurrencyIds !== []) {
                throw new RuntimeException(
                    "AiBillingService: org #{$orgId} has usage costs in currencies ["
                    . implode(', ', array_map(static fn ($id) => $id ?? 'null', $wrongCurrencyIds))
                    . "] but balance currency_id={$balanceCurrencyId}. No FX — price tokens in balance currency."
                );
            }

            $totalCost = (float) AiUsageRawLog::query()
                ->whereIn('id', $rawIds)
                ->sum('calculated_cost');

            if ($totalCost <= 0) {
                AiUsageRawLog::query()
                    ->whereIn('id', $rawIds)
                    ->update(['processed' => true]);
                return;
            }

            $wasEnabled = (bool) $balance->is_agent_enabled;

            ['limited' => $fromLimited, 'ai_balance' => $fromAi] = $this->distributeDeduction(
                $balance,
                $totalCost
            );

            $usageLog = AiUsageLog::query()->create([
                'organization_id' => $orgId,
                'currency_id' => $balance->currency_id,
                'total_cost' => $totalCost,
                'deducted_from_limited' => $fromLimited,
                'deducted_from_ai_balance' => $fromAi,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'created_at' => now(),
            ]);

            AiUsageRawLog::query()
                ->whereIn('id', $rawIds)
                ->update([
                    'processed' => true,
                    'ai_usage_log_id' => $usageLog->id,
                ]);

            $timeRange = $periodStart->format('H:i') . '–' . $periodEnd->format('H:i');

            if ($fromLimited > 0) {
                AiBalanceTransaction::query()->create([
                    'organization_id' => $orgId,
                    'currency_id' => $balance->currency_id,
                    'type' => AiBalanceTransaction::TYPE_DEDUCTION,
                    'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
                    'amount' => $fromLimited,
                    'description' => "Списание с лимита за использование ИИ ({$timeRange})",
                ]);
            }

            if ($fromAi > 0) {
                AiBalanceTransaction::query()->create([
                    'organization_id' => $orgId,
                    'currency_id' => $balance->currency_id,
                    'type' => AiBalanceTransaction::TYPE_DEDUCTION,
                    'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
                    'amount' => $fromAi,
                    'description' => "Списание со свободного ИИ-баланса за использование ИИ ({$timeRange})",
                ]);
            }

            $balance->save();

            $this->checkAndToggleAgent($balance, $wasEnabled);
        });
    }

    public function distributeDeduction(AiBalance $balance, float $amount): array
    {
        $remaining = round($amount, 6);
        $fromLimited = 0.0;
        $fromAi = 0.0;

        $limited = (float) $balance->limited_balance;
        if ($limited > 0 && $remaining > 0) {
            $take = min($limited, $remaining);
            $balance->limited_balance = round($limited - $take, 4);
            $fromLimited = $take;
            $remaining = round($remaining - $take, 6);
        }

        if ($remaining > 0) {
            $spendable = $balance->spendableWalletAmount();
            if ($spendable > 0) {
                $take = min($spendable, $remaining);
                $balance->ai_balance = round((float) $balance->ai_balance - $take, 4);
                $fromAi = $take;
                $remaining = round($remaining - $take, 6);
            }
        }

        // Usage already happened — leftover goes to technical overdraft on limited.
        if ($remaining > 0) {
            $balance->limited_balance = round((float) $balance->limited_balance - $remaining, 4);
            $fromLimited = round($fromLimited + $remaining, 4);
        }

        return ['limited' => $fromLimited, 'ai_balance' => $fromAi];
    }

    /**
     * Agent stays on while there is limited OR spendable wallet left.
     * Reserved prepaid months do not keep the agent online.
     */
    public function checkAndToggleAgent(AiBalance $balance, bool $wasPreviouslyEnabled): void
    {
        $available = $balance->availableForUsageAmount();

        if ($available <= 0 && $wasPreviouslyEnabled) {
            $balance->is_agent_enabled = false;
            $balance->saveQuietly();
            AiAgentToggleJob::dispatchSync(
                organizationId: (int) $balance->organization_id,
                enabled: false
            );
        } elseif ($available > 0 && ! $wasPreviouslyEnabled) {
            $balance->is_agent_enabled = true;
            $balance->saveQuietly();
            AiAgentToggleJob::dispatchSync(
                organizationId: (int) $balance->organization_id,
                enabled: true
            );
        }
    }
}
