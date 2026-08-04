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
        'cost_per_1m_input',
        'cost_per_1m_output',
        'is_active',
    ];

    protected $casts = [
        'cost_per_1m_input'  => 'decimal:6',
        'cost_per_1m_output' => 'decimal:6',
        'is_active'          => 'boolean',
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
     * Актуальная цена на дату (по умолчанию сегодня).
     * При дублях — последняя по id; при нескольких валютах предпочитаем USD.
     */
    public function resolvePriceAt(?string $onDate = null, ?int $preferCurrencyId = null): ?AiModelPrice
    {
        $prices = $this->prices()
            ->current($onDate)
            ->with('currency')
            ->orderByDesc('id')
            ->get()
            ->groupBy('currency_id')
            ->map(fn ($group) => $group->sortByDesc('id')->first())
            ->values();

        if ($prices->isEmpty()) {
            return null;
        }

        if ($preferCurrencyId) {
            $preferred = $prices->firstWhere('currency_id', $preferCurrencyId);
            if ($preferred) {
                return $preferred;
            }
        }

        $usd = $prices->first(function (AiModelPrice $price) {
            $name = strtoupper((string) ($price->currency?->name ?? ''));
            $symbol = strtoupper((string) ($price->currency?->symbol_code ?? ''));

            return $name === 'USD' || $symbol === 'USD' || $symbol === '$';
        });

        return $usd ?? $prices->sortByDesc('id')->first();
    }
}
