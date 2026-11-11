<?php

namespace App\Models\Ai;

use App\Models\CommercialOffer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialOfferAiItem extends Model
{
    use HasFactory;

    public const DEMO_DAYS = 3;

    public const DEMO_ALLOWED_REQUEST_TYPES = [
        'connection',
        'connection_extra_services',
    ];

    protected $fillable = [
        'commercial_offer_id',
        'plan_id',
        'period_months',
        'demo_days',
        'gift_months',
        'unit_price',
        'discount_percent',
        'implementation_discount_percent',
        'partner_percent',
        'original_price',
        'total_price',
        'current_month_amount',
        'balance_topup',
    ];

    protected $casts = [
        'period_months' => 'integer',
        'demo_days' => 'integer',
        'gift_months' => 'integer',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'implementation_discount_percent' => 'decimal:4',
        'partner_percent' => 'decimal:2',
        'original_price' => 'decimal:4',
        'total_price' => 'decimal:4',
        'current_month_amount' => 'decimal:4',
        'balance_topup' => 'decimal:4',
    ];

    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiTariffPlan::class, 'plan_id');
    }

    /**
     * Акция 3+3 и 6+6 выключена.
     */
    public static function giftMonthsForConnectionPeriod(int $periodMonths): int
    {
        return 0;
    }

    /**
     * Акция доступна, если тариф не базовый и организация ещё не использовала / не оплачивала ИИ.
     */
    public static function isGiftPromoEligible(?Organization $organization, bool $isBaseTariff): bool
    {
        if ($isBaseTariff) {
            return false;
        }

        if (! $organization) {
            return true;
        }

        if ((bool) ($organization->ai_gift_promo_used ?? false)) {
            return false;
        }

        return ! AiSubscription::query()
            ->where('organization_id', $organization->id)
            ->exists();
    }

    public static function resolveGiftMonths(
        int $periodMonths,
        ?Organization $organization,
        bool $isBaseTariff
    ): int {
        if (! self::isGiftPromoEligible($organization, $isBaseTariff)) {
            return 0;
        }

        return self::giftMonthsForConnectionPeriod($periodMonths);
    }

    public function effectiveExtraMonths(): int
    {
        return max(0, (int) $this->period_months) + max(0, (int) $this->gift_months);
    }

    public function isDemo(): bool
    {
        return max(0, (int) ($this->demo_days ?? 0)) > 0;
    }

    /** Цена демо: тариф / 30 × дни. */
    public static function demoAmount(float $unitPrice, int $days = self::DEMO_DAYS): float
    {
        return round(max(0, $unitPrice) / 30 * max(0, $days), 4);
    }

    public static function allowsDemoForRequestType(?string $requestType): bool
    {
        return in_array((string) $requestType, self::DEMO_ALLOWED_REQUEST_TYPES, true);
    }

    /** Сумма к оплате по AI: текущий месяц + доп. месяцы / демо + баланс ИИ */
    public function chargedTotal(): float
    {
        return round(
            (float) $this->current_month_amount
            + (float) $this->total_price
            + (float) $this->balance_topup,
            4
        );
    }
}
