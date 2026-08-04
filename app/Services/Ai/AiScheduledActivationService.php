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

class AiScheduledActivationService
{
    /**
     * Calculate the date on which the agent should auto-activate
     * when org has ai_balance but not enough for a full remaining-month purchase yet.
     */
    public function calculateScheduledActivation(AiBalance $balance, AiTariffPlan $plan): ?Carbon
    {
        $aiBalance = (float) $balance->ai_balance;

        if ($aiBalance <= 0) {
            return null;
        }

        $now = Carbon::now('Asia/Dushanbe');
        $daysInMonth = (int) $now->daysInMonth;
        $dailyRate = $plan->monthlyLimit() / max(1, $daysInMonth); // throws if no price

        $daysCovered = (int) floor($aiBalance / $dailyRate);
        if ($daysCovered <= 0) {
            return null;
        }

        $endOfMonth = $now->copy()->endOfMonth()->startOfDay();
        $activationDate = $endOfMonth->copy()->subDays($daysCovered - 1);

        if ($activationDate->lte($now->copy()->startOfDay())) {
            return $now->copy()->startOfDay();
        }

        return $activationDate;
    }

    /**
     * Check all balances with a scheduled activation date and activate those due today.
     */
    public function checkAndActivate(): void
    {
        $today = Carbon::today('Asia/Dushanbe');

        AiBalance::query()
            ->whereDate('scheduled_activation_at', '<=', $today)
            ->whereNotNull('scheduled_activation_at')
            ->where('is_agent_enabled', false)
            ->each(function (AiBalance $balance) use ($today): void {
                try {
                    DB::transaction(function () use ($balance, $today): void {
                        $balance = AiBalance::query()
                            ->where('id', $balance->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $balance || $balance->is_agent_enabled) {
                            return;
                        }

                        if (Carbon::parse($balance->scheduled_activation_at)->gt($today)) {
                            return;
                        }

                        $subscription = AiSubscription::query()
                            ->where('organization_id', $balance->organization_id)
                            ->active()
                            ->where('expires_at', '>=', $today)
                            ->orderByDesc('id')
                            ->with('plan.currentPrice')
                            ->first();

                        if (! $subscription || ! $subscription->plan) {
                            return;
                        }

                        $now = Carbon::now('Asia/Dushanbe');
                        $daysInMonth = (int) $now->daysInMonth;
                        $dayOfMonth = (int) $today->day;
                        $daysLeft = $daysInMonth - $dayOfMonth + 1;
                        $fullCost = $subscription->plan->monthlyLimit(); // throws if no price
                        $cost = round(($fullCost / max(1, $daysInMonth)) * $daysLeft, 4);
                        $ai = (float) $balance->ai_balance;

                        if ($ai < $cost) {
                            $this->recalculate($balance);
                            return;
                        }

                        $balance->ai_balance = round($ai - $cost, 4);
                        $balance->is_agent_enabled = true;
                        $balance->scheduled_activation_at = null;
                        $balance->save();

                        AiBalanceTransaction::query()->create([
                            'organization_id' => $balance->organization_id,
                            'currency_id' => $balance->currency_id,
                            'type' => AiBalanceTransaction::TYPE_MONTHLY_PURCHASE,
                            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
                            'amount' => $cost,
                            'description' => "Покупка пропорционального лимита при отложенной активации ({$daysLeft}/{$daysInMonth} дней)",
                        ]);

                        $balance->increment('limited_balance', $cost);

                        AiBalanceTransaction::query()->create([
                            'organization_id' => $balance->organization_id,
                            'currency_id' => $balance->currency_id,
                            'type' => AiBalanceTransaction::TYPE_TARIFF_GRANT_PRORATED,
                            'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
                            'amount' => $cost,
                            'description' => "Начисление лимита при активации: {$daysLeft} из {$daysInMonth} дней",
                        ]);

                        AiAgentToggleJob::dispatchSync(
                            organizationId: (int) $balance->organization_id,
                            enabled: true
                        );

                        Log::info('AiScheduledActivationService: agent activated, prorated limit granted', [
                            'organization_id' => $balance->organization_id,
                            'cost' => $cost,
                            'days_left' => $daysLeft,
                        ]);
                    });
                } catch (\Throwable $e) {
                    Log::error('AiScheduledActivationService: activation failed', [
                        'organization_id' => $balance->organization_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }

    /**
     * Recalculate and persist the scheduled activation date.
     * Works both with and without an active subscription.
     */
    public function recalculate(AiBalance $balance): void
    {
        // Уже есть рабочий лимит — расписание не нужно.
        if ((float) $balance->limited_balance > 0 || $balance->is_agent_enabled) {
            if ($balance->scheduled_activation_at !== null) {
                $balance->scheduled_activation_at = null;
                $balance->save();
            }
            return;
        }

        $subscription = AiSubscription::query()
            ->where('organization_id', $balance->organization_id)
            ->active()
            ->where('expires_at', '>=', now())
            ->with('plan.currentPrice')
            ->orderByDesc('id')
            ->first();

        $plan = $subscription?->plan;

        if (! $plan) {
            $lastSubscription = AiSubscription::query()
                ->where('organization_id', $balance->organization_id)
                ->orderByDesc('id')
                ->with('plan.currentPrice')
                ->first();
            $plan = $lastSubscription?->plan;
        }

        if (! $plan) {
            if ($balance->scheduled_activation_at !== null) {
                $balance->scheduled_activation_at = null;
                $balance->save();
            }
            return;
        }

        $activationDate = $this->calculateScheduledActivation($balance, $plan);
        $balance->scheduled_activation_at = $activationDate?->toDateString();
        $balance->save();
    }
}
