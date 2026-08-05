<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $fillable = [
        'name',
        'provider',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static array $providers = [
        'openai'   => 'OpenAI',
        'deepseek' => 'DeepSeek',
        'gemini'   => 'Gemini',
        'claude'   => 'Claude',
    ];

    public function getProviderLabelAttribute(): string
    {
        return self::$providers[$this->provider] ?? $this->provider;
    }

    public function tariffPlans(): HasMany
    {
        return $this->hasMany(AiTariffPlan::class, 'ai_model_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(AiModelPrice::class, 'ai_model_id')->orderByDesc('start_date');
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(AiModelPrice::class, 'ai_model_id')
            ->current()
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    /**
     * Актуальная цена на дату строго в указанной валюте.
     * Без currency_id / без прайса в этой валюте — null (никакого USD-fallback и FX).
     */
    public function resolvePriceAt(?string $onDate = null, ?int $currencyId = null): ?AiModelPrice
    {
        if (! $currencyId) {
            return null;
        }

        return $this->prices()
            ->current($onDate)
            ->where('currency_id', $currencyId)
            ->orderByDesc('id')
            ->first();
    }
}
