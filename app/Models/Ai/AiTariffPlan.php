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
     * Monthly limit (= price_monthly from current price row).
     * Без актуальной цены — ошибка (нельзя подставлять legacy/0).
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
     * Currency of the current tariff price.
     * Без актуальной цены — ошибка (нельзя брать legacy currency_id).
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
