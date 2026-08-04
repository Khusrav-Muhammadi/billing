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

        // Идемпотентность: повторный paid по тому же КП не должен дублировать кошелёк.
        $alreadyRegistered = AiSubscription::query()
            ->where('commercial_offer_id', $offer->id)
            ->exists();

        if ($alreadyRegistered) {
            Log::info('AiSubscriptionRegistryService: already registered for offer', [
                'commercial_offer_id' => $offer->id,
            ]);
            return;
        }

        DB::transaction(function () use ($offer, $aiItem): void {
            $plan = AiTariffPlan::query()
                ->with('currentPrice')
                ->findOrFail($aiItem->plan_id);
            $orgId = (int) $offer->organization_id;

            $this->createOrRenewSubscription($orgId, $plan, $aiItem, $offer->id);
            $balance = $this->ensureBalance($orgId, $plan);

            // 1. В кошелёк — полный лимит за оплаченные месяцы (без коммерческой скидки),
            //    чтобы скидка в КП не уменьшала число доступных месяцев.
            $this->creditPaidAmount($balance, $aiItem, $plan);

            // 2. Купить пропорциональный лимит на остаток текущего месяца.
            $granted = $this->grantProratedLimit($balance, $plan);

            // 2b. Запас на ИИ-баланс (свободный кошелёк) — отдельно от оплаты периода.
            $this->creditBalanceTopUp($balance, $aiItem);

            // 3. Агент включаем только если limited реально начислен
            //    ИЛИ есть свободный запас на балансе.
            $balance->refresh();
            if (($granted && (float) $balance->limited_balance > 0)
                || $balance->availableForUsageAmount() > 0) {
                $balance->is_agent_enabled = true;
                $balance->scheduled_activation_at = null;
                $balance->save();

                AiAgentToggleJob::dispatchSync(
                    organizationId: $orgId,
                    enabled: true
                );
            } else {
                $balance->is_agent_enabled = false;
                $balance->save();
                $this->activationService->recalculate($balance);
            }
        });
    }

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

        $today = Carbon::today('Asia/Dushanbe');
        $periodMonths = max(1, (int) $aiItem->period_months);

        if ($existing && $existing->expires_at && $existing->expires_at->gt($today)) {
            $startedAt = $existing->expires_at->copy()->addDay()->startOfDay();
        } else {
            $startedAt = $today->copy()->startOfDay();
        }

        // Старт 4 авг, 3 мес → до 31 окт.
        $expiresAt = $startedAt->copy()
            ->addMonthsNoOverflow($periodMonths - 1)
            ->endOfMonth()
            ->endOfDay();

        if ($existing) {
            $existing->update(['status' => false]);
        }

        return AiSubscription::query()->create([
            'organization_id' => $orgId,
            'plan_id' => $plan->id,
            'status' => true,
            'period_months' => $periodMonths,
            'price_paid' => $aiItem->total_price,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'commercial_offer_id' => $offerId,
        ]);
    }

    private function ensureBalance(int $orgId, AiTariffPlan $plan): AiBalance
    {
        $currencyId = $plan->currencyId();
        if (! $currencyId) {
            throw new \RuntimeException("AI plan #{$plan->id} has no current price/currency.");
        }

        return AiBalance::query()->firstOrCreate(
            ['organization_id' => $orgId],
            [
                'currency_id' => $currencyId,
                'limited_balance' => 0,
                'ai_balance' => 0,
                'is_agent_enabled' => false,
            ]
        );
    }

    private function creditPaidAmount(AiBalance $balance, CommercialOfferAiItem $aiItem, AiTariffPlan $plan): void
    {
        $periodMonths = max(1, (int) $aiItem->period_months);
        $monthlyLimit = $plan->monthlyLimit();
        $fromPlan = round($monthlyLimit * $periodMonths, 4);
        $fromItem = (float) $aiItem->original_price;

        $amount = $fromItem > 0 ? $fromItem : $fromPlan;

        if ($amount <= 0) {
            Log::warning('AiSubscriptionRegistryService: zero credit amount', [
                'organization_id' => $balance->organization_id,
                'plan_id' => $plan->id,
            ]);
            return;
        }

        $balance->increment('ai_balance', $amount);
        $balance->refresh();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_PAYMENT,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => "Пополнение кошелька ИИ ({$periodMonths} мес., тариф «{$plan->name}»)",
        ]);

        Log::info('AiSubscriptionRegistryService: ai_balance credited', [
            'organization_id' => $balance->organization_id,
            'amount' => $amount,
        ]);
    }

    /** @return bool true if limited_balance was granted */
    private function grantProratedLimit(AiBalance $balance, AiTariffPlan $plan): bool
    {
        $now = Carbon::now('Asia/Dushanbe');
        $daysInMonth = (int) $now->daysInMonth;
        $dayOfMonth = (int) $now->day;
        $daysLeft = $daysInMonth - $dayOfMonth + 1;
        $fullCost = $plan->monthlyLimit();

        if ($fullCost <= 0 || $daysInMonth <= 0) {
            return false;
        }

        $cost = round(($fullCost / $daysInMonth) * $daysLeft, 4);
        $ai = (float) $balance->ai_balance;

        if ($ai < $cost) {
            $this->activationService->recalculate($balance);
            Log::info('AiSubscriptionRegistryService: ai_balance insufficient for prorated grant', [
                'organization_id' => $balance->organization_id,
                'ai_balance' => $ai,
                'required' => $cost,
            ]);
            return false;
        }

        $balance->ai_balance = round($ai - $cost, 4);
        $balance->save();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_MONTHLY_PURCHASE,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $cost,
            'description' => "Списание за {$daysLeft}/{$daysInMonth} дней месяца (пропорциональный лимит)",
        ]);

        $balance->increment('limited_balance', $cost);

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_TARIFF_GRANT_PRORATED,
            'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
            'amount' => $cost,
            'description' => "Пропорциональное начисление лимита: {$daysLeft} из {$daysInMonth} дней",
        ]);

        Log::info('AiSubscriptionRegistryService: prorated limit granted', [
            'organization_id' => $balance->organization_id,
            'cost' => $cost,
            'days_left' => $daysLeft,
        ]);

        return true;
    }


    /**
     * Credit optional balance_topup into free ai_balance (TYPE_TOPUP).
     */
    private function creditBalanceTopUp(AiBalance $balance, CommercialOfferAiItem $aiItem): void
    {
        $amount = max(0, (float) ($aiItem->balance_topup ?? 0));
        if ($amount <= 0) {
            return;
        }

        $balance->increment('ai_balance', $amount);
        $balance->refresh();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_TOPUP,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => 'Запас на ИИ-баланс при подключении (из КП)',
        ]);

        Log::info('AiSubscriptionRegistryService: balance_topup credited', [
            'organization_id' => $balance->organization_id,
            'amount' => $amount,
        ]);
    }
}
