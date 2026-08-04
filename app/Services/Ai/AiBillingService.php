<?php

namespace App\Services\Ai;

use App\Jobs\Ai\AiAgentToggleJob;
use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiUsageLog;
use App\Models\Ai\AiUsageRawLog;
use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiBillingService
{
    /**
     * Run a 30-minute billing cycle for a single organization.
     */
    public function processOrganization(int $orgId): void
    {
        DB::transaction(function () use ($orgId): void {
            /** @var AiBalance $balance */
            $balance = AiBalance::query()
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                return;
            }

            $periodStart = now()->subMinutes(30);
            $periodEnd = now();

            $rawQuery = AiUsageRawLog::query()
                ->where('organization_id', $orgId)
                ->unprocessed();

            $rawCostByGroup = $rawQuery->clone()
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

            if ($totalCostInBalanceCurrency === null) {
                Log::error('AiBillingService: exchange rate not found, billing deferred', [
                    'organization_id' => $orgId,
                ]);
                return;
            }

            if ($totalCostInBalanceCurrency <= 0) {
                $rawQuery->update(['processed' => true]);
                return;
            }

            $wasEnabled = (bool) $balance->is_agent_enabled;

            ['limited' => $fromLimited, 'ai_balance' => $fromAi] = $this->distributeDeduction(
                $balance,
                $totalCostInBalanceCurrency
            );

            $rawQuery->update(['processed' => true]);

            AiUsageLog::query()->create([
                'organization_id' => $orgId,
                'currency_id' => $balance->currency_id,
                'total_cost' => $totalCostInBalanceCurrency,
                'deducted_from_limited' => $fromLimited,
                'deducted_from_ai_balance' => $fromAi,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'created_at' => now(),
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

    /**
     * Deduct token usage:
     *  1) limited_balance
     *  2) spendable part of ai_balance (top-ups / leftovers)
     *     — NEVER touch reserved money for future prepaid months
     *  3) leftover → technical overdraft on limited_balance
     *
     * Returns ['limited' => float, 'ai_balance' => float].
     */
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
     * Returns null if any required exchange rate is missing.
     */
    private function convertToBalanceCurrency(array $costByGroup, int $balanceCurrencyId, Carbon $date): ?float
    {
        $total = 0.0;

        foreach ($costByGroup as $currencyId => $cost) {
            $cost = (float) $cost;

            // Без валюты стоимости нельзя считать — иначе сумма уйдёт в чужой валюте.
            if ($currencyId === null || $currencyId === '' || (int) $currencyId <= 0) {
                Log::error('AiBillingService: usage cost without currency_id, billing deferred', [
                    'to_currency_id' => $balanceCurrencyId,
                    'cost' => $cost,
                    'date' => $date->toDateTimeString(),
                ]);
                return null;
            }

            if ((int) $currencyId === $balanceCurrencyId) {
                $total += $cost;
                continue;
            }

            $rate = ExchangeRate::query()
                ->where('currency_id', $currencyId)
                ->where('created_at', '<=', $date)
                ->orderByDesc('created_at')
                ->value('kurs');

            if ($rate === null) {
                Log::error('AiBillingService: no exchange rate found', [
                    'from_currency_id' => $currencyId,
                    'to_currency_id' => $balanceCurrencyId,
                    'date' => $date->toDateTimeString(),
                ]);
                return null;
            }

            $total += $cost * (float) $rate;
        }

        return round($total, 6);
    }
}
