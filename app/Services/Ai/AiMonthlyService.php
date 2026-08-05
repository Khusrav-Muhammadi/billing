<?php

namespace App\Services\Ai;

use App\Jobs\Ai\AiAgentToggleJob;
use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiTariffPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiMonthlyService
{
    /**
     * Run at the very end of the month (23:59, Asia/Dushanbe).
     * Burns remaining limited_balance into company profit.
     */
    public function processEndOfMonth(): void
    {
        $now = Carbon::now('Asia/Dushanbe');

        if (! $now->isLastOfMonth()) {
            Log::info('AiMonthlyService::processEndOfMonth called on non-last day, skipping.');
            return;
        }

        AiBalance::query()
            ->where('limited_balance', '>', 0)
            ->each(function (AiBalance $balance): void {
                DB::transaction(function () use ($balance): void {
                    $balance = AiBalance::query()
                        ->where('id', $balance->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $balance || (float) $balance->limited_balance <= 0) {
                        return;
                    }

                    $expired = (float) $balance->limited_balance;
                    $balance->limited_balance = 0;
                    // После сгорания лимита агент должен быть выключен до покупки нового месяца.
                    $wasEnabled = (bool) $balance->is_agent_enabled;
                    $balance->is_agent_enabled = false;
                    $balance->save();

                    AiBalanceTransaction::query()->create([
                        'organization_id' => $balance->organization_id,
                        'currency_id' => $balance->currency_id,
                        'type' => AiBalanceTransaction::TYPE_EXPIRED_PROFIT,
                        'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
                        'amount' => $expired,
                        'description' => 'Сгорание лимитированного остатка в конце месяца',
                    ]);

                    if ($wasEnabled) {
                        AiAgentToggleJob::dispatch(
                            organizationId: (int) $balance->organization_id,
                            enabled: false
                        );
                    }

                    Log::info('AiMonthlyService: limited balance expired', [
                        'organization_id' => $balance->organization_id,
                        'amount' => $expired,
                    ]);
                });
            });
    }

    /**
     * Run at the beginning of the month (00:01, Asia/Dushanbe).
     * Covers any debt from ai_balance, then grants monthly limit.
     */
    public function processStartOfMonth(): void
    {
        AiBalance::query()
            ->with(['organization'])
            ->each(function (AiBalance $balance): void {
                try {
                    DB::transaction(function () use ($balance): void {
                        $balance = AiBalance::query()
                            ->where('id', $balance->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $balance) {
                            return;
                        }

                        $this->coverDebt($balance);

                        $subscription = AiSubscription::query()
                            ->where('organization_id', $balance->organization_id)
                            ->active()
                            ->where('started_at', '<=', now())
                            ->where('expires_at', '>=', now())
                            ->with('plan.currentPrice')
                            ->orderByDesc('id')
                            ->first();

                        if ($subscription) {
                            $this->grantStartOfMonthLimit($balance, $subscription);
                        }
                    });
                } catch (\Throwable $e) {
                    // Не продолжаем с нулём/фолбеком — фиксируем ошибку и идём к следующей org.
                    Log::error('AiMonthlyService: start of month failed', [
                        'organization_id' => $balance->organization_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    /**
     * At the start of every new month, try to "purchase" the monthly limit
     * from the client's ai_balance wallet.
     */
    private function grantStartOfMonthLimit(AiBalance $balance, AiSubscription $subscription): void
    {
        $plan = $subscription->plan;
        if (! $plan) {
            return;
        }

        $fullCost = $plan->monthlyLimitForCurrency((int) $balance->currency_id);
        $ai = (float) $balance->ai_balance;

        if ($ai < $fullCost) {
            app(AiScheduledActivationService::class)->recalculate($balance);

            if ($balance->is_agent_enabled) {
                $balance->is_agent_enabled = false;
                $balance->save();
                AiAgentToggleJob::dispatch(
                    organizationId: (int) $balance->organization_id,
                    enabled: false
                );
            }

            Log::info('AiMonthlyService: ai_balance insufficient for monthly purchase', [
                'organization_id' => $balance->organization_id,
                'ai_balance' => $ai,
                'required' => $fullCost,
                'scheduled_at' => $balance->scheduled_activation_at,
            ]);
            return;
        }

        $balance->ai_balance = round($ai - $fullCost, 4);
        $balance->scheduled_activation_at = null;
        $balance->save();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_MONTHLY_PURCHASE,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $fullCost,
            'description' => "Покупка месячного лимита из кошелька ИИ ({$plan->name})",
        ]);

        $balance->increment('limited_balance', $fullCost);

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_TARIFF_GRANT,
            'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
            'amount' => $fullCost,
            'description' => "Ежемесячное начисление лимита по тарифу «{$plan->name}»",
        ]);

        // После покупки лимита агент должен работать.
        if (! $balance->is_agent_enabled) {
            $balance->is_agent_enabled = true;
            $balance->save();
            AiAgentToggleJob::dispatchSync(
                organizationId: (int) $balance->organization_id,
                enabled: true
            );
        }

        Log::info('AiMonthlyService: monthly limit purchased and granted', [
            'organization_id' => $balance->organization_id,
            'amount' => $fullCost,
        ]);
    }

    public function grantMonthlyLimit(AiBalance $balance, AiTariffPlan $plan, bool $prorated): void
    {
        $fullCost = $plan->monthlyLimitForCurrency((int) $balance->currency_id);

        if ($prorated) {
            $now = Carbon::now('Asia/Dushanbe');
            $daysInMonth = (int) $now->daysInMonth;
            $dayOfMonth = (int) $now->day;
            $daysLeft = $daysInMonth - $dayOfMonth + 1;
            $grantAmount = round(($fullCost / $daysInMonth) * $daysLeft, 4);
            $type = AiBalanceTransaction::TYPE_TARIFF_GRANT_PRORATED;
            $description = "Пропорциональное начисление лимита: {$daysLeft}/{$daysInMonth} дней";
        } else {
            $grantAmount = $fullCost;
            $type = AiBalanceTransaction::TYPE_TARIFF_GRANT;
            $description = "Ежемесячное начисление лимита по тарифу «{$plan->name}»";
        }

        $balance->increment('limited_balance', $grantAmount);

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => $type,
            'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
            'amount' => $grantAmount,
            'description' => $description,
        ]);

        Log::info('AiMonthlyService: granted monthly limit', [
            'organization_id' => $balance->organization_id,
            'amount' => $grantAmount,
            'prorated' => $prorated,
        ]);
    }

    private function coverDebt(AiBalance $balance): void
    {
        $limited = (float) $balance->limited_balance;

        if ($limited >= 0) {
            return;
        }

        $debt = abs($limited);
        // Погашаем долг только из свободной части кошелька — резерв месяцев не трогаем.
        $spendable = $balance->spendableWalletAmount();
        if ($spendable <= 0) {
            return;
        }

        $covered = min($debt, $spendable);
        $balance->ai_balance = round((float) $balance->ai_balance - $covered, 4);
        $balance->limited_balance = round($limited + $covered, 4);
        $balance->save();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_DEBT_COVER,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $covered,
            'description' => 'Погашение долга лимитированного баланса из свободного ИИ-счёта',
        ]);
    }
}
