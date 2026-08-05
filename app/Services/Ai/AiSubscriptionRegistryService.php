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

        $aiItem = CommercialOfferAiItem::query()
            ->where('commercial_offer_id', $offer->id)
            ->first();

        if (! $aiItem) {
            return;
        }

        try {
            DB::transaction(function () use ($offer, $aiItem): void {
                // Идемпотентность внутри транзакции + row lock.
                $alreadyRegistered = AiSubscription::query()
                    ->where('commercial_offer_id', $offer->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyRegistered) {
                    Log::info('AiSubscriptionRegistryService: already registered for offer', [
                        'commercial_offer_id' => $offer->id,
                    ]);
                    return;
                }

                $plan = AiTariffPlan::query()
                    ->with('currentPrice')
                    ->findOrFail($aiItem->plan_id);
                $orgId = (int) $offer->organization_id;

                $this->createOrRenewSubscription($orgId, $plan, $aiItem, $offer->id);
                $balance = $this->ensureBalance($orgId, $plan);

                // 1. В кошелёк — полный лимит за оплаченные месяцы (без коммерческой скидки),
                //    чтобы скидка в КП не уменьшала число доступных месяцев.
                $this->creditPaidAmount($balance, $aiItem, $plan);

                // 2. Купить пропорциональный лимит на остаток текущего месяца
                //    из оплаченной суммы КП (original_price / months), не из «другого» прайса тарифа.
                $periodMonths = max(1, (int) $aiItem->period_months);
                $monthlyFromPayment = round((float) $aiItem->original_price / $periodMonths, 4);
                $granted = $this->grantProratedLimit($balance, $plan, $monthlyFromPayment);

                if (! $granted) {
                    throw new RuntimeException(
                        "AiSubscriptionRegistryService: failed to grant prorated limit for org #{$orgId}, "
                        . "plan #{$plan->id}, monthly_from_payment={$monthlyFromPayment}, "
                        . "ai_balance={$balance->ai_balance}."
                    );
                }

                // 2b. Запас на ИИ-баланс (свободный кошелёк) — отдельно от оплаты периода.
                $this->creditBalanceTopUp($balance, $aiItem);

                // 3. Агент включаем: limited уже начислен (или есть spendable после topup).
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

    /**
     * Откат AI-регистрации при правке/отмене paid статуса КП.
     * Снимает original_price + balance_topup с баланса и удаляет подписку по commercial_offer_id.
     */
    public function reverse(CommercialOffer $offer): void
    {
        DB::transaction(function () use ($offer): void {
            /** @var AiSubscription|null $subscription */
            $subscription = AiSubscription::query()
                ->where('commercial_offer_id', $offer->id)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return;
            }

            $aiItem = CommercialOfferAiItem::query()
                ->where('commercial_offer_id', $offer->id)
                ->first();

            if (! $aiItem) {
                throw new RuntimeException(
                    "AiSubscriptionRegistryService::reverse: commercial_offer_ai_items missing for offer #{$offer->id}."
                );
            }

            $originalPrice = round((float) $aiItem->original_price, 4);
            $topUp = round(max(0, (float) ($aiItem->balance_topup ?? 0)), 4);
            $clawback = round($originalPrice + $topUp, 4);

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

            // Удаляем подписку, чтобы повторный paid мог зарегистрироваться снова.
            $subscription->delete();

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
        int $offerId
    ): AiSubscription {
        $existing = AiSubscription::query()
            ->where('organization_id', $orgId)
            ->where('status', true)
            ->orderByDesc('expires_at')
            ->lockForUpdate()
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

    /**
     * Balance currency must match plan currency.
     * Empty balance (0/0) may switch currency; non-zero mismatch → exception.
     */
    private function ensureBalance(int $orgId, AiTariffPlan $plan): AiBalance
    {
        $planCurrencyId = $plan->currencyId();

        /** @var AiBalance|null $balance */
        $balance = AiBalance::query()
            ->where('organization_id', $orgId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            AiBalance::query()->create([
                'organization_id' => $orgId,
                'currency_id' => $planCurrencyId,
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

        if ((int) $balance->currency_id !== $planCurrencyId) {
            $hasFunds = abs((float) $balance->limited_balance) > 0.0001
                || abs((float) $balance->ai_balance) > 0.0001;

            if ($hasFunds) {
                throw new RuntimeException(
                    "AiSubscriptionRegistryService: currency mismatch for org #{$orgId}: "
                    . "balance currency_id={$balance->currency_id}, plan currency_id={$planCurrencyId}. "
                    . "Cannot credit into a different currency while balance is non-zero."
                );
            }

            $balance->currency_id = $planCurrencyId;
            $balance->save();
        }

        return $balance;
    }

    private function creditPaidAmount(AiBalance $balance, CommercialOfferAiItem $aiItem, AiTariffPlan $plan): void
    {
        $periodMonths = max(1, (int) $aiItem->period_months);
        // Кошелёк пополняем по снапшоту КП (original_price = без скидки периода).
        // Без суммы в КП — ошибка, не подставляем «примерно» из текущего прайса.
        $amount = round((float) $aiItem->original_price, 4);
        if ($amount <= 0) {
            throw new RuntimeException(
                "AI item for offer has empty original_price; cannot credit wallet (plan #{$plan->id}, months={$periodMonths})."
            );
        }
        // Сверка с актуальным прайсом тарифа — если прайса нет, monthlyLimit() бросит.
        $plan->monthlyLimit();

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

    /**
     * @param  float|null  $monthlyOverride  Месячная база из КП (original_price/months).
     *                                       Если null — текущий прайс тарифа.
     * @return bool true if limited_balance was granted
     */
    private function grantProratedLimit(AiBalance $balance, AiTariffPlan $plan, ?float $monthlyOverride = null): bool
    {
        $now = Carbon::now('Asia/Dushanbe');
        $daysInMonth = (int) $now->daysInMonth;
        $dayOfMonth = (int) $now->day;
        $daysLeft = $daysInMonth - $dayOfMonth + 1;

        // Прайса тарифа всё равно должен существовать (валюта/актуальность).
        $planMonthly = $plan->monthlyLimit();
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

    private function isUniqueCommercialOfferViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'ai_subscriptions_commercial_offer_id_unique')
            || (str_contains($message, 'commercial_offer_id') && str_contains($message, 'Duplicate'));
    }
}
