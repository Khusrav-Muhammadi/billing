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

    /** Текущая актуальная цена */
    public function currentPrice(): HasOne
    {
        return $this->hasOne(AiTariffPlanPrice::class, 'plan_id')
            ->current()
            ->latest('start_date');
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AiSubscription::class, 'plan_id');
    }

    /**
     * Monthly limit (= price_monthly from current price row).
     * Falls back to legacy column if still present.
     */
    public function monthlyLimit(): float
    {
        $price = $this->relationLoaded('currentPrice')
            ? $this->currentPrice
            : $this->currentPrice()->first();

        if ($price && (float) $price->price_monthly > 0) {
            return (float) $price->price_monthly;
        }

        return (float) ($this->getAttribute('included_limit_balance') ?? 0);
    }

    /**
     * Currency of the current tariff price.
     */
    public function currencyId(): ?int
    {
        $price = $this->relationLoaded('currentPrice')
            ? $this->currentPrice
            : $this->currentPrice()->first();

        if ($price?->currency_id) {
            return (int) $price->currency_id;
        }

        $legacy = $this->getAttribute('currency_id');

        return $legacy !== null ? (int) $legacy : null;
    }
}
