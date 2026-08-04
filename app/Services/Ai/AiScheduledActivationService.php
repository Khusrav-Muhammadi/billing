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
     * when the org has no active subscription but has ai_balance > 0.
     */
    public function calculateScheduledActivation(AiBalance $balance, AiTariffPlan $plan): ?Carbon
    {
        $aiBalance = (float) $balance->ai_balance;

        if ($aiBalance <= 0) {
            return null;
        }

        $now = Carbon::now('Asia/Dushanbe');
        $daysInMonth = (int) $now->daysInMonth;
        $dailyRate = (float) $plan->included_limit_balance / $daysInMonth;

        if ($dailyRate <= 0) {
            return null;
        }

        $daysCovered = (int) floor($aiBalance / $dailyRate);
        $endOfMonth = $now->copy()->endOfMonth()->startOfDay();

        $activationDate = $endOfMonth->subDays($daysCovered - 1);

        if ($activationDate->lte($now->startOfDay())) {
            return $now->startOfDay();
        }

        return $activationDate;
    }

    /**
     * Check all balances with a scheduled activation date and activate those due today.
     * On activation: purchase the prorated limit for the remaining days of the month
     * from ai_balance, then enable the agent.
     */
    public function checkAndActivate(): void
    {
        $today = Carbon::today('Asia/Dushanbe');

        AiBalance::query()
            ->whereDate('scheduled_activation_at', '<=', $today)
            ->whereNotNull('scheduled_activation_at')
            ->where('is_agent_enabled', false)
            ->each(function (AiBalance $balance) use ($today): void {
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
                        ->with('plan')
                        ->first();

                    if (! $subscription || ! $subscription->plan) {
                        return;
                    }

                    // Calculate cost for remaining days in this month
                    $now         = Carbon::now('Asia/Dushanbe');
                    $daysInMonth = (int) $now->daysInMonth;
                    $dayOfMonth  = (int) $today->day;
                    $daysLeft    = $daysInMonth - $dayOfMonth + 1;
                    $fullCost    = (float) $subscription->plan->included_limit_balance;
                    $cost        = round(($fullCost / $daysInMonth) * $daysLeft, 4);
                    $ai          = (float) $balance->ai_balance;

                    if ($ai < $cost) {
                        // Still not enough — recalculate for a later date
                        $this->recalculate($balance);
                        return;
                    }

                    // Deduct from ai_balance
                    $balance->ai_balance              = round($ai - $cost, 4);
                    $balance->is_agent_enabled        = true;
                    $balance->scheduled_activation_at = null;
                    $balance->save();

                    // Record purchase
                    \App\Models\Ai\AiBalanceTransaction::query()->create([
                        'organization_id' => $balance->organization_id,
                        'currency_id'     => $balance->currency_id,
                        'type'            => \App\Models\Ai\AiBalanceTransaction::TYPE_MONTHLY_PURCHASE,
                        'target_balance'  => \App\Models\Ai\AiBalanceTransaction::TARGET_AI_BALANCE,
                        'amount'          => $cost,
                        'description'     => "Покупка пропорционального лимита при отложенной активации ({$daysLeft}/{$daysInMonth} дней)",
                    ]);

                    // Grant limited_balance
                    $balance->increment('limited_balance', $cost);

                    \App\Models\Ai\AiBalanceTransaction::query()->create([
                        'organization_id' => $balance->organization_id,
                        'currency_id'     => $balance->currency_id,
                        'type'            => \App\Models\Ai\AiBalanceTransaction::TYPE_TARIFF_GRANT_PRORATED,
                        'target_balance'  => \App\Models\Ai\AiBalanceTransaction::TARGET_LIMITED,
                        'amount'          => $cost,
                        'description'     => "Начисление лимита при активации: {$daysLeft} из {$daysInMonth} дней",
                    ]);

                    AiAgentToggleJob::dispatchSync(
                        organizationId: (int) $balance->organization_id,
                        enabled: true
                    );

                    Log::info('AiScheduledActivationService: agent activated, prorated limit granted', [
                        'organization_id' => $balance->organization_id,
                        'cost'            => $cost,
                        'days_left'       => $daysLeft,
                    ]);
                });
            });
    }

    /**
     * Recalculate and persist the scheduled activation date for an organization.
     * Call this when ai_balance is topped up or subscription changes.
     */
    public function recalculate(AiBalance $balance): void
    {
        $hasActiveSubscription = AiSubscription::query()
            ->where('organization_id', $balance->organization_id)
            ->active()
            ->where('expires_at', '>=', now())
            ->exists();

        if ($hasActiveSubscription) {
            if ($balance->scheduled_activation_at !== null) {
                $balance->scheduled_activation_at = null;
                $balance->save();
            }
            return;
        }

        $lastSubscription = AiSubscription::query()
            ->where('organization_id', $balance->organization_id)
            ->orderByDesc('id')
            ->with('plan')
            ->first();

        if (! $lastSubscription || ! $lastSubscription->plan) {
            return;
        }

        $activationDate = $this->calculateScheduledActivation($balance, $lastSubscription->plan);

        $balance->scheduled_activation_at = $activationDate?->toDateString();
        $balance->save();
    }
}
