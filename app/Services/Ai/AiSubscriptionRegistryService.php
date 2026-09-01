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
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Tariff;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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

        $aiItems = CommercialOfferAiItem::query()
            ->where('commercial_offer_id', $offer->id)
            ->orderBy('id')
            ->get();

        if ($aiItems->isEmpty()) {
            return;
        }

        $offer->loadMissing('tariff:id,name');
        // Базовый тариф больше не блокирует ИИ — только акцию подарочных месяцев.

        try {
            DB::transaction(function () use ($offer, $aiItems): void {
                $orgId = (int) $offer->organization_id;
                $offerCurrencyId = $this->resolveOfferCurrencyId($offer);
                $balance = null;

                foreach ($aiItems as $aiItem) {
                    $alreadyRegistered = AiSubscription::query()
                        ->where('commercial_offer_id', $offer->id)
                        ->where('plan_id', $aiItem->plan_id)
                        ->lockForUpdate()
                        ->exists();

                    if ($alreadyRegistered) {
                        continue;
                    }

                    $plan = AiTariffPlan::query()->findOrFail($aiItem->plan_id);
                    $plan->monthlyLimitForCurrency($offerCurrencyId);

                    $this->createOrRenewSubscription($orgId, $plan, $aiItem, $offer);
                    $balance = $this->ensureBalance($orgId, $offerCurrencyId);

                    if (
                        ! $aiItem->isDemo()
                        && AiTariffPlan::normalizeCategory((string) ($plan->category ?? '')) === AiTariffPlan::CATEGORY_CHAT
                    ) {
                        Organization::query()
                            ->whereKey($orgId)
                            ->update(['ai_gift_promo_used' => true]);
                    }

                    if ($aiItem->isDemo()) {
                        $this->creditAndGrantDemo($balance, $aiItem, $plan);
                    } else {
                        $this->creditAndGrantCurrentMonth($balance, $aiItem, $plan);

                        if ((int) $aiItem->period_months > 0 && (float) $aiItem->original_price > 0) {
                            $this->creditPaidAmount($balance, $aiItem, $plan);
                        }

                        $this->creditGiftMonths($balance, $aiItem, $plan);
                    }
                    $this->creditBalanceTopUp($balance, $aiItem);
                }

                if (! $balance) {
                    return;
                }

                $balance->refresh();
                if ((float) $balance->limited_balance > 0
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
        } catch (QueryException $e) {
            // UNIQUE(commercial_offer_id) — проигравший concurrent paid не должен кредитовать.
            if ($this->isUniqueCommercialOfferViolation($e)) {
                Log::info('AiSubscriptionRegistryService: concurrent register lost unique race', [
                    'commercial_offer_id' => $offer->id,
                ]);
                return;
            }
            throw $e;
        }
    }

    public function reverse(CommercialOffer $offer): void
    {
        DB::transaction(function () use ($offer): void {
            $subscriptions = AiSubscription::query()
                ->where('commercial_offer_id', $offer->id)
                ->lockForUpdate()
                ->get();

            if ($subscriptions->isEmpty()) {
                return;
            }

            $aiItems = CommercialOfferAiItem::query()
                ->where('commercial_offer_id', $offer->id)
                ->get();

            if ($aiItems->isEmpty()) {
                throw new RuntimeException(
                    "AiSubscriptionRegistryService::reverse: commercial_offer_ai_items missing for offer #{$offer->id}."
                );
            }

            $clawback = 0.0;
            foreach ($aiItems as $aiItem) {
                $currentMonth = round(max(0, (float) ($aiItem->current_month_amount ?? 0)), 4);
                $originalPrice = round((float) $aiItem->original_price, 4);
                $giftMonths = max(0, (int) ($aiItem->gift_months ?? 0));
                $giftAmount = 0.0;
                if ($giftMonths > 0) {
                    $unit = round((float) $aiItem->unit_price, 4);
                    $giftAmount = round($unit * $giftMonths, 4);
                }
                $topUp = round(max(0, (float) ($aiItem->balance_topup ?? 0)), 4);
                $clawback += $currentMonth + $originalPrice + $giftAmount + $topUp;
            }
            $clawback = round($clawback, 4);

            if ($clawback <= 0) {
                throw new RuntimeException(
                    "AiSubscriptionRegistryService::reverse: clawback amount empty for offer #{$offer->id}."
                );
            }

            $orgId = (int) $offer->organization_id;

            /** @var AiBalance|null $balance */
            $balance = AiBalance::query()
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new RuntimeException(
                    "AiSubscriptionRegistryService::reverse: ai_balances missing for org #{$orgId} (offer #{$offer->id})."
                );
            }

            if ((int) $balance->currency_id <= 0) {
                throw new RuntimeException(
                    "AiSubscriptionRegistryService::reverse: ai_balances has no currency_id (org #{$orgId})."
                );
            }

            $remaining = $clawback;
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
                $balance->ai_balance = round((float) $balance->ai_balance - $remaining, 4);
                $fromAi = $remaining;
            }

            $wasEnabled = (bool) $balance->is_agent_enabled;
            $balance->save();

            if ($fromLimited > 0) {
                AiBalanceTransaction::query()->create([
                    'organization_id' => $orgId,
                    'currency_id' => $balance->currency_id,
                    'type' => AiBalanceTransaction::TYPE_REVERSAL,
                    'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
                    'amount' => $fromLimited,
                    'description' => "Откат ИИ-начисления с лимита (КП #{$offer->id})",
                ]);
            }

            if ($fromAi > 0) {
                AiBalanceTransaction::query()->create([
                    'organization_id' => $orgId,
                    'currency_id' => $balance->currency_id,
                    'type' => AiBalanceTransaction::TYPE_REVERSAL,
                    'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
                    'amount' => $fromAi,
                    'description' => "Откат ИИ-начисления с кошелька (КП #{$offer->id})",
                ]);
            }

            foreach ($subscriptions as $subscription) {
                $subscription->delete();
            }

            if ($wasEnabled && $balance->availableForUsageAmount() <= 0) {
                $balance->is_agent_enabled = false;
                $balance->save();
                AiAgentToggleJob::dispatchSync(
                    organizationId: $orgId,
                    enabled: false
                );
            }

            Log::info('AiSubscriptionRegistryService: reversed AI registration', [
                'commercial_offer_id' => $offer->id,
                'organization_id' => $orgId,
                'clawback' => $clawback,
                'from_limited' => $fromLimited,
                'from_ai_balance' => $fromAi,
            ]);
        });
    }

    private function createOrRenewSubscription(
        int $orgId,
        AiTariffPlan $plan,
        CommercialOfferAiItem $aiItem,
        CommercialOffer $offer
    ): AiSubscription {
        $existing = AiSubscription::query()
            ->where('organization_id', $orgId)
            ->where('plan_id', $plan->id)
            ->where('status', true)
            ->orderByDesc('expires_at')
            ->lockForUpdate()
            ->first();

        $today = Carbon::today('Asia/Dushanbe');
        $demoDays = max(0, (int) ($aiItem->demo_days ?? 0));

        if ($existing && $existing->expires_at && $existing->expires_at->gt($today)) {
            $startedAt = $existing->expires_at->copy()->addDay()->startOfDay();
        } else {
            $startedAt = $today->copy()->startOfDay();
        }

        if ($demoDays > 0) {
            $calendarMonths = 0;
            $expiresAt = $startedAt->copy()
                ->addDays($demoDays - 1)
                ->endOfDay();
        } else {
            $extraMonths = max(0, (int) $aiItem->period_months);
            // Срок из снапшота КП (gift_months уже посчитан при сохранении с учётом тарифа/акции).
            $extraMonths += max(0, (int) ($aiItem->gift_months ?? 0));
            $calendarMonths = $extraMonths + 1;

            $expiresAt = $startedAt->copy()
                ->addMonthsNoOverflow($calendarMonths - 1)
                ->endOfMonth()
                ->endOfDay();
        }

        if ($existing) {
            $existing->update(['status' => false]);
        }

        return AiSubscription::query()->create([
            'organization_id' => $orgId,
            'plan_id' => $plan->id,
            'status' => true,
            'period_months' => $calendarMonths,
            'price_paid' => $aiItem->chargedTotal(),
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'commercial_offer_id' => $offer->id,
        ]);
    }


    private function resolveOfferCurrencyId(CommercialOffer $offer): int
    {
        $code = strtoupper(trim((string) ($offer->currency ?: $offer->payable_currency ?: '')));
        if ($code === '') {
            throw new RuntimeException(
                "AiSubscriptionRegistryService: offer #{$offer->id} has empty currency/payable_currency."
            );
        }

        $currencyId = Currency::query()
            ->whereRaw('UPPER(TRIM(symbol_code)) = ?', [$code])
            ->value('id');

        if (! $currencyId) {
            throw new RuntimeException(
                "AiSubscriptionRegistryService: currency [{$code}] not found for offer #{$offer->id}."
            );
        }

        return (int) $currencyId;
    }

    private function ensureBalance(int $orgId, int $currencyId): AiBalance
    {
        if ($currencyId <= 0) {
            throw new RuntimeException(
                "AiSubscriptionRegistryService: invalid currency_id for org #{$orgId}."
            );
        }

        /** @var AiBalance|null $balance */
        $balance = AiBalance::query()
            ->where('organization_id', $orgId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            AiBalance::query()->create([
                'organization_id' => $orgId,
                'currency_id' => $currencyId,
                'limited_balance' => 0,
                'ai_balance' => 0,
                'is_agent_enabled' => false,
            ]);

            $balance = AiBalance::query()
                ->where('organization_id', $orgId)
                ->lockForUpdate()
                ->firstOrFail();

            return $balance;
        }

        if ((int) $balance->currency_id !== $currencyId) {
            $hasFunds = abs((float) $balance->limited_balance) > 0.0001
                || abs((float) $balance->ai_balance) > 0.0001;

            if ($hasFunds) {
                throw new RuntimeException(
                    "AiSubscriptionRegistryService: currency mismatch for org #{$orgId}: "
                    . "balance currency_id={$balance->currency_id}, offer currency_id={$currencyId}. "
                    . "Cannot credit into a different currency while balance is non-zero."
                );
            }

            $balance->currency_id = $currencyId;
            $balance->save();
        }

        return $balance;
    }

    private function creditPaidAmount(AiBalance $balance, CommercialOfferAiItem $aiItem, AiTariffPlan $plan): void
    {
        $periodMonths = max(0, (int) $aiItem->period_months);
        // Кошелёк пополняем по снапшоту КП (original_price = без скидки периода) только за +N мес.
        $amount = round((float) $aiItem->original_price, 4);
        if ($amount <= 0 || $periodMonths <= 0) {
            return;
        }
        // Прайс тарифа в валюте баланса обязан существовать.
        $plan->monthlyLimitForCurrency((int) $balance->currency_id);

        $balance->increment('ai_balance', $amount);
        $balance->refresh();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_PAYMENT,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => "Пополнение кошелька ИИ (+{$periodMonths} мес., тариф «{$plan->name}»)",
        ]);

        Log::info('AiSubscriptionRegistryService: ai_balance credited', [
            'organization_id' => $balance->organization_id,
            'amount' => $amount,
        ]);
    }

    /**
     * Подарочные месяцы кладём в кошелёк бесплатно (unit × gift_months),
     * иначе start-of-month не сможет купить лимит и агент выключится.
     */
    private function creditGiftMonths(AiBalance $balance, CommercialOfferAiItem $aiItem, AiTariffPlan $plan): void
    {
        $giftMonths = max(0, (int) ($aiItem->gift_months ?? 0));
        if ($giftMonths <= 0) {
            return;
        }

        $unit = round((float) $aiItem->unit_price, 4);
        if ($unit <= 0) {
            $unit = round($plan->monthlyLimitForCurrency((int) $balance->currency_id), 4);
        }

        $amount = round($unit * $giftMonths, 4);
        if ($amount <= 0) {
            return;
        }

        $plan->monthlyLimitForCurrency((int) $balance->currency_id);

        $balance->increment('ai_balance', $amount);
        $balance->refresh();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_PAYMENT,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => "Подарочные месяцы ИИ (+{$giftMonths} мес., тариф «{$plan->name}», скидка 100%)",
        ]);

        Log::info('AiSubscriptionRegistryService: gift months credited to ai_balance', [
            'organization_id' => $balance->organization_id,
            'gift_months' => $giftMonths,
            'amount' => $amount,
        ]);
    }

    private function creditAndGrantDemo(AiBalance $balance, CommercialOfferAiItem $aiItem, AiTariffPlan $plan): void
    {
        $days = max(0, (int) ($aiItem->demo_days ?? 0));
        $amount = round((float) $aiItem->total_price, 4);
        if ($amount <= 0) {
            $amount = CommercialOfferAiItem::demoAmount((float) $aiItem->unit_price, $days);
        }

        if ($days <= 0 || $amount <= 0) {
            throw new RuntimeException(
                "AI item missing demo amount; cannot grant demo limit (plan #{$plan->id})."
            );
        }

        $plan->monthlyLimitForCurrency((int) $balance->currency_id);

        $balance->increment('ai_balance', $amount);
        $balance->refresh();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_PAYMENT,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => "Оплата демо ИИ ({$days} дня, тариф «{$plan->name}»)",
        ]);

        $ai = (float) $balance->ai_balance;
        if ($ai + 0.0001 < $amount) {
            throw new RuntimeException(
                "AiSubscriptionRegistryService: ai_balance insufficient for demo "
                . "(have={$ai}, need={$amount}, org #{$balance->organization_id})."
            );
        }

        $balance->ai_balance = round($ai - $amount, 4);
        $balance->save();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_MONTHLY_PURCHASE,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => "Списание за демо ({$days} дня)",
        ]);

        $balance->increment('limited_balance', $amount);

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_TARIFF_GRANT_PRORATED,
            'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
            'amount' => $amount,
            'description' => "Начисление лимита за демо ({$days} дня)",
        ]);

        Log::info('AiSubscriptionRegistryService: demo granted', [
            'organization_id' => $balance->organization_id,
            'amount' => $amount,
            'demo_days' => $days,
        ]);
    }

    private function creditAndGrantCurrentMonth(AiBalance $balance, CommercialOfferAiItem $aiItem, AiTariffPlan $plan): void
    {
        $amount = round(max(0, (float) ($aiItem->current_month_amount ?? 0)), 4);
        if ($amount <= 0) {
            throw new RuntimeException(
                "AI item missing current_month_amount; cannot grant current-month limit (plan #{$plan->id})."
            );
        }

        $plan->monthlyLimitForCurrency((int) $balance->currency_id);

        $balance->increment('ai_balance', $amount);
        $balance->refresh();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_PAYMENT,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => "Оплата текущего месяца ИИ (пропорция, тариф «{$plan->name}»)",
        ]);

        $ai = (float) $balance->ai_balance;
        if ($ai + 0.0001 < $amount) {
            throw new RuntimeException(
                "AiSubscriptionRegistryService: ai_balance insufficient for current month "
                . "(have={$ai}, need={$amount}, org #{$balance->organization_id})."
            );
        }

        $balance->ai_balance = round($ai - $amount, 4);
        $balance->save();

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_MONTHLY_PURCHASE,
            'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
            'amount' => $amount,
            'description' => 'Списание за текущий месяц (пропорциональный лимит)',
        ]);

        $balance->increment('limited_balance', $amount);

        AiBalanceTransaction::query()->create([
            'organization_id' => $balance->organization_id,
            'currency_id' => $balance->currency_id,
            'type' => AiBalanceTransaction::TYPE_TARIFF_GRANT_PRORATED,
            'target_balance' => AiBalanceTransaction::TARGET_LIMITED,
            'amount' => $amount,
            'description' => 'Начисление лимита за текущий месяц',
        ]);

        Log::info('AiSubscriptionRegistryService: current month granted', [
            'organization_id' => $balance->organization_id,
            'amount' => $amount,
        ]);
    }

    private function grantProratedLimit(AiBalance $balance, AiTariffPlan $plan, ?float $monthlyOverride = null): bool
    {
        $now = Carbon::now('Asia/Dushanbe');
        $daysInMonth = (int) $now->daysInMonth;
        $dayOfMonth = (int) $now->day;
        $daysLeft = $daysInMonth - $dayOfMonth + 1;

        // Прайс тарифа в валюте баланса обязан существовать (валюта/актуальность).
        $planMonthly = $plan->monthlyLimitForCurrency((int) $balance->currency_id);
        $fullCost = $monthlyOverride !== null && $monthlyOverride > 0
            ? round($monthlyOverride, 4)
            : $planMonthly;

        if ($daysInMonth <= 0) {
            throw new RuntimeException('Invalid daysInMonth while granting prorated AI limit.');
        }

        $cost = round(($fullCost / $daysInMonth) * $daysLeft, 4);
        $ai = (float) $balance->ai_balance;

        if ($ai < $cost) {
            $this->activationService->recalculate($balance);
            Log::info('AiSubscriptionRegistryService: ai_balance insufficient for prorated grant', [
                'organization_id' => $balance->organization_id,
                'ai_balance' => $ai,
                'required' => $cost,
                'full_cost' => $fullCost,
                'plan_monthly' => $planMonthly,
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
            'description' => 'Баланс ИИ при подключении ',
        ]);

    }

    private function isUniqueCommercialOfferViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'ai_subscriptions_commercial_offer_id_unique')
            || str_contains($message, 'ai_subscriptions_offer_plan_unique')
            || (str_contains($message, 'commercial_offer_id') && str_contains($message, 'Duplicate'));
    }
}
