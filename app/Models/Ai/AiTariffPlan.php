<?php

namespace App\Models\Ai;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function aiModel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function tokenPricing(): HasOne
    {
        return $this->hasOne(AiTokenPricing::class, 'model_name', 'model')
            ->whereNull('effective_to');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AiSubscription::class, 'plan_id');
    }
}
