<?php

namespace App\Services\Ai;

use App\Jobs\Ai\AiAgentToggleJob;
use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiUsageLog;
use App\Models\Ai\AiUsageRawLog;
use App\Models\CurrencyRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiBillingService
{
    /**
     * Run a 30-minute billing cycle for a single organization.
     */
    public function processOrganization(int $orgId): void
    {
        DB::transaction(function () use ($orgId): void {
            /** @var AiBalance|null $balance */
            $balance = AiBalance::query()
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                return;
            }

            if ((int) $balance->currency_id <= 0) {
                throw new RuntimeException(
                    "AiBillingService: ai_balances #{$balance->id} has no currency_id (org #{$orgId})."
                );
            }

            $periodStart = now()->subMinutes(30);
            $periodEnd = now();

            // Фиксируем конкретные строки до SUM/UPDATE — иначе логи, вставленные
            // между агрегацией и mark processed, «сгорают» без списания.
            $rawIds = AiUsageRawLog::query()
                ->where('organization_id', $orgId)
                ->unprocessed()
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            if ($rawIds->isEmpty()) {
                return;
            }

            $rawCostByGroup = AiUsageRawLog::query()
                ->whereIn('id', $rawIds)
                ->selectRaw('cost_currency_id, SUM(calculated_cost) as total')
                ->groupBy('cost_currency_id')
                ->pluck('total', 'cost_currency_id')
                ->toArray();

            if (empty($rawCostByGroup)) {
                return;
            }

            $totalCostInBalanceCurrency = $this->convertToBalanceCurrency(
                $rawCostByGroup,
                (int) $balance->currency_id,
                $periodEnd
            );

            if ($totalCostInBalanceCurrency <= 0) {
                AiUsageRawLog::query()
                    ->whereIn('id', $rawIds)
                    ->update(['processed' => true]);
                return;
            }

            $wasEnabled = (bool) $balance->is_agent_enabled;

            ['limited' => $fromLimited, 'ai_balance' => $fromAi] = $this->distributeDeduction(
                $balance,
                $totalCostInBalanceCurrency
            );

            $usageLog = AiUsageLog::query()->create([
                'organization_id' => $orgId,
                'currency_id' => $balance->currency_id,
                'total_cost' => $totalCostInBalanceCurrency,
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

    /**
     * Convert a map of {currency_id => cost} to the balance currency.
     * Missing currency / rate → RuntimeException (no silent defer / fallback).
     */
    private function convertToBalanceCurrency(array $costByGroup, int $balanceCurrencyId, Carbon $date): float
    {
        if ($balanceCurrencyId <= 0) {
            throw new RuntimeException('AiBillingService: balance currency_id is required for FX conversion.');
        }

        $total = 0.0;

        foreach ($costByGroup as $currencyId => $cost) {
            $cost = (float) $cost;

            if ($currencyId === null || $currencyId === '' || (int) $currencyId <= 0) {
                throw new RuntimeException(
                    "AiBillingService: usage cost without cost_currency_id (cost={$cost}, to_currency_id={$balanceCurrencyId})."
                );
            }

            $fromCurrencyId = (int) $currencyId;

            if ($fromCurrencyId === $balanceCurrencyId) {
                $total += $cost;
                continue;
            }

            $rateDate = $date->toDateString();

            $rate = CurrencyRate::query()
                ->where('base_currency_id', $fromCurrencyId)
                ->where('quote_currency_id', $balanceCurrencyId)
                ->whereNotNull('rate_date')
                ->whereDate('rate_date', '<=', $rateDate)
                ->orderByDesc('rate_date')
                ->orderByDesc('id')
                ->value('rate');

            if ($rate === null) {
                throw new RuntimeException(
                    "AiBillingService: no currency_rates row for {$fromCurrencyId} → {$balanceCurrencyId} on {$rateDate}."
                );
            }

            $rate = (float) $rate;
            if ($rate <= 0) {
                throw new RuntimeException(
                    "AiBillingService: invalid currency rate ({$rate}) for {$fromCurrencyId} → {$balanceCurrencyId} on {$rateDate}."
                );
            }

            $total += $cost * $rate;
        }

        return round($total, 6);
    }
}
