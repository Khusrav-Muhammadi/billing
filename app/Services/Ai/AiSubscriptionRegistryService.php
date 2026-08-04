<?php

namespace App\Services\Ai;

use App\Jobs\Ai\AiAgentToggleJob;
use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use App\Models\Ai\AiSubscription;
use App\Models\Ai\AiTariffPlan;
use App\Models\Ai\CommercialOfferAiItem;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiSubscriptionRegistryService
{
    public function __construct(
        private readonly AiScheduledActivationService $activationService,
    ) {}

    public function register(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string) $status->status !== 'paid') {
            return;
        }

        $aiItem = CommercialOfferAiItem::query()
            ->where('commercial_offer_id', $offer->id)
            ->first();

        if (! $aiItem) {
            return;
        }

        DB::transaction(function () use ($offer, $aiItem): void {
            $plan  = AiTariffPlan::query()->findOrFail($aiItem->plan_id);
            $orgId = (int) $offer->organization_id;

            $subscription = $this->createOrRenewSubscription($orgId, $plan, $aiItem, $offer->id);

            $balance = $this->ensureBalance($orgId, $plan);

            // 1. Credit the full paid amount into ai_balance (this is the client's wallet).
            $this->creditPaidAmount($balance, $aiItem);

            // 2. Grant a prorated limited_balance for the current calendar month,
            //    deducting the cost from ai_balance.
            $this->grantProratedLimit($balance, $plan, $subscription);

            // 3. Activate the agent immediately (balance now has tokens).
            $balance->is_agent_enabled       = true;
            $balance->scheduled_activation_at = null;
            $balance->save();

            AiAgentToggleJob::dispatchSync(
                organizationId: $orgId,
                enabled: true
            );
        });
    }

    // ──────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────

    private function createOrRenewSubscription(
        int $orgId,
        AiTariffPlan $plan,
        CommercialOfferAiItem $aiItem,
        int $offerId
    ): AiSubscription {
        $existing = AiSubscription::query()
            ->where('organization_id', $orgId)
            ->where('status', true)
            ->orderByDesc('expires_at')
            ->first();

        $today = Carbon::today();

        if ($existing && $existing->expires_at->gt($today)) {
            $startedAt = $existing->expires_at->addDay();
        } else {
            $startedAt = $today;
        }

        // Subscription covers exactly the current calendar month.
        // expires_at = last day of the month in which it starts.
        $expiresAt = $startedAt->copy()->endOfMonth()->endOfDay();

        if ($existing) {
            $existing->update(['status' => false]);
        }

        return AiSubscription::query()->create([
            'organization_id'  => $orgId,
            'plan_id'          => $plan->id,
            'status'           => true,
            'period_months'    => $aiItem->period_months,
            'price_paid'       => $aiItem->total_price,
            'started_at'       => $startedAt,
            'expires_at'       => $expiresAt,
            'commercial_offer_id' => $offerId,
        ]);
    }

    private function ensureBalance(int $orgId, AiTariffPlan $plan): AiBalance
    {
        return AiBalance::query()->firstOrCreate(
            ['organization_id' => $orgId],
            [
                'currency_id'    => $plan->currency_id,
                'limited_balance' => 0,
                'ai_balance'     => 0,
                'is_agent_enabled' => false,
            ]
        );
    }

    /**
     * Credit the client's prepaid amount into ai_balance.
     * ai_balance is the wallet from which monthly limits are purchased.
     */
    private function creditPaidAmount(AiBalance $balance, CommercialOfferAiItem $aiItem): void
    {
        $amount = (float) $aiItem->total_price;
        if ($amount <= 0) {
            return;
        }

        $balance->increment('ai_balance', $amount);

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id'     => $balance->currency_id,
            'type'            => AiBalanceTransaction::TYPE_PAYMENT,
            'target_balance'  => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount'          => $amount,
            'description'     => "Пополнение кошелька ИИ (оплата подписки)",
        ]);

        Log::info('AiSubscriptionRegistryService: ai_balance credited', [
            'organization_id' => $balance->organization_id,
            'amount'          => $amount,
        ]);
    }

    /**
     * Grant a prorated limited_balance for the remaining days of the current month,
     * deducting the equivalent cost from ai_balance.
     *
     * If ai_balance is insufficient for even one day, skip and set scheduled activation.
     */
    private function grantProratedLimit(AiBalance $balance, AiTariffPlan $plan, AiSubscription $subscription): void
    {
        $now          = Carbon::now('Asia/Dushanbe');
        $daysInMonth  = (int) $now->daysInMonth;
        $dayOfMonth   = (int) $now->day;
        $daysLeft     = $daysInMonth - $dayOfMonth + 1;
        $dailyCost    = (float) $plan->included_limit_balance / $daysInMonth;
        $cost         = round($dailyCost * $daysLeft, 4);

        $ai = (float) $balance->ai_balance;

        if ($ai < $cost) {
            // Not enough — calculate when the balance will cover remaining days.
            $this->activationService->recalculate($balance);
            Log::info('AiSubscriptionRegistryService: ai_balance insufficient for prorated grant', [
                'organization_id' => $balance->organization_id,
                'ai_balance'      => $ai,
                'required'        => $cost,
            ]);
            return;
        }

        // Deduct cost from ai_balance.
        $balance->ai_balance = round($ai - $cost, 4);
        $balance->save();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id'     => $balance->currency_id,
            'type'            => AiBalanceTransaction::TYPE_MONTHLY_PURCHASE,
            'target_balance'  => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount'          => $cost,
            'description'     => "Списание за {$daysLeft}/{$daysInMonth} дней месяца (пропорциональный лимит)",
        ]);

        // Credit limited_balance.
        $balance->increment('limited_balance', $cost);

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id'     => $balance->currency_id,
            'type'            => AiBalanceTransaction::TYPE_TARIFF_GRANT_PRORATED,
            'target_balance'  => AiBalanceTransaction::TARGET_LIMITED,
            'amount'          => $cost,
            'description'     => "Пропорциональное начисление лимита: {$daysLeft} из {$daysInMonth} дней",
        ]);

        Log::info('AiSubscriptionRegistryService: prorated limit granted', [
            'organization_id' => $balance->organization_id,
            'cost'            => $cost,
            'days_left'       => $daysLeft,
        ]);
    }
}
