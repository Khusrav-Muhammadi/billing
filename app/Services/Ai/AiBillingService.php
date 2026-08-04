<?php

namespace App\Services\Ai;

use App\Jobs\Ai\AiAgentToggleJob;
use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiSubscription;
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

            if ($fromLimited > 0 || $fromAi > 0) {
                AiBalanceTransaction::query()->create([
                    'organization_id' => $orgId,
                    'currency_id' => $balance->currency_id,
                    'type' => AiBalanceTransaction::TYPE_DEDUCTION,
                    'target_balance' => $fromAi > 0
                        ? AiBalanceTransaction::TARGET_AI_BALANCE
                        : AiBalanceTransaction::TARGET_LIMITED,
                    'amount' => $totalCostInBalanceCurrency,
                    'description' => "Списание за использование ИИ ({$periodStart->format('H:i')}–{$periodEnd->format('H:i')})",
                ]);
            }

            $balance->save();

            $this->checkAndToggleAgent($balance, $wasEnabled);
        });
    }

    /**
     * Deduct token usage cost ONLY from limited_balance.
     *
     * ai_balance is a prepaid wallet reserved for purchasing future monthly limits
     * and must NEVER be touched by the 30-minute token billing cycle.
     * If limited_balance runs out, it goes into technical overdraft (negative),
     * and the agent will be disabled by checkAndToggleAgent().
     *
     * Returns ['limited' => float, 'ai_balance' => float].
     */
    public function distributeDeduction(AiBalance $balance, float $amount): array
    {
        $balance->limited_balance = round((float) $balance->limited_balance - $amount, 4);

        return ['limited' => $amount, 'ai_balance' => 0.0];
    }

    /**
     * Enable or disable the AI agent based on limited_balance only.
     *
     * ai_balance is reserved for future month purchases and must not influence
     * whether the agent can process tokens right now.
     */
    public function checkAndToggleAgent(AiBalance $balance, bool $wasPreviouslyEnabled): void
    {
        $limited = (float) $balance->limited_balance;

        if ($limited <= 0 && $wasPreviouslyEnabled) {
            $balance->is_agent_enabled = false;
            $balance->saveQuietly();
            AiAgentToggleJob::dispatchSync(
                organizationId: (int) $balance->organization_id,
                enabled: false
            );
        } elseif ($limited > 0 && ! $wasPreviouslyEnabled) {
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

            if ((int) $currencyId === $balanceCurrencyId || $currencyId === null) {
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
