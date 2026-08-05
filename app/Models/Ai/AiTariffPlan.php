<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiTariffPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ai_model_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AiTariffPlanPeriod::class, 'plan_id')->orderBy('months');
    }

    /** Только активные периоды (valid_to IS NULL) */
    public function activePeriods(): HasMany
    {
        return $this->hasMany(AiTariffPlanPeriod::class, 'plan_id')
            ->whereNull('valid_to')
            ->orderBy('months');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(AiTariffPlanPrice::class, 'plan_id')->orderByDesc('start_date');
    }

    /** Текущая актуальная цена (при дублях на одну дату — последняя по id) */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(AiTariffPlanPrice::class, 'plan_id')
            ->current()
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AiSubscription::class, 'plan_id');
    }

    public function commercialOfferItems(): HasMany
    {
        return $this->hasMany(CommercialOfferAiItem::class, 'plan_id');
    }

    /**
     * Текущая цена тарифа строго в указанной валюте.
     * Без прайса в этой валюте — null (никакого FX / fallback на другую валюту).
     */
    public function currentPriceForCurrency(int $currencyId): ?AiTariffPlanPrice
    {
        if ($currencyId <= 0) {
            return null;
        }

        return $this->prices()
            ->current()
            ->where('currency_id', $currencyId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Monthly limit (= price_monthly) в указанной валюте.
     * Без актуальной цены в этой валюте — ошибка.
     */
    public function monthlyLimitForCurrency(int $currencyId): float
    {
        $price = $this->currentPriceForCurrency($currencyId);

        if (! $price || (float) $price->price_monthly <= 0) {
            throw new \RuntimeException(
                "AI plan #{$this->id} ({$this->name}) has no current monthly price "
                . "in currency_id={$currencyId} (no FX)."
            );
        }

        return (float) $price->price_monthly;
    }

    /**
     * @deprecated Используйте monthlyLimitForCurrency($currencyId) — без валюты прайс неоднозначен.
     */
    public function monthlyLimit(): float
    {
        $price = $this->relationLoaded('currentPrice')
            ? $this->currentPrice
            : $this->currentPrice()->first();

        if (! $price || (float) $price->price_monthly <= 0) {
            throw new \RuntimeException(
                "AI plan #{$this->id} ({$this->name}) has no current monthly price."
            );
        }

        return (float) $price->price_monthly;
    }

    /**
     * @deprecated Используйте валюту КП / баланса, не «любой» прайс тарифа.
     */
    public function currencyId(): int
    {
        $price = $this->relationLoaded('currentPrice')
            ? $this->currentPrice
            : $this->currentPrice()->first();

        if (! $price?->currency_id) {
            throw new \RuntimeException(
                "AI plan #{$this->id} ({$this->name}) has no current price currency."
            );
        }

        return (int) $price->currency_id;
    }
}
