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
        'category',
        'daily_minutes',
        'ai_model_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'daily_minutes' => 'integer',
    ];

    public const CATEGORY_CHAT = 'chat';

    public const CATEGORY_CALL_ANALYSE = 'call_analyse';

    /** Id тарифов анализа звонков в проде не меняются. */
    public const CALL_ANALYSE_START_ID = 9;

    public const CALL_ANALYSE_PREMIUM_ID = 10;

    public const CALL_ANALYSE_VIP_ID = 11;

    /** Start 30 мин, Premium 1 час, Vip 3 часа в день. */
    public const CALL_ANALYSE_DAILY_MINUTES = [
        self::CALL_ANALYSE_START_ID => 30,
        self::CALL_ANALYSE_PREMIUM_ID => 60,
        self::CALL_ANALYSE_VIP_ID => 180,
    ];

    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_CHAT => 'ИИ-Агент чатов',
            self::CATEGORY_CALL_ANALYSE => 'ИИ-Агент анализа звонков',
        ];
    }

    public static function normalizeCategory(?string $value): string
    {
        $raw = mb_strtolower(trim((string) $value));

        return match ($raw) {
            'call_analyse',
            'call-analyse',
            'call_analysis',
            'call-analysis',
            'calls',
            'call',
            'analyse',
            'analysis' => self::CATEGORY_CALL_ANALYSE,
            default => self::CATEGORY_CHAT,
        };
    }

    public function setCategoryAttribute(?string $value): void
    {
        $this->attributes['category'] = self::normalizeCategory($value);
    }

    /**
     * Минут анализа в день.
     * Если в БД пусто — берём значение по стабильному id тарифа.
     */
    public function resolvedDailyMinutes(): int
    {
        $stored = (int) ($this->daily_minutes ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        return self::CALL_ANALYSE_DAILY_MINUTES[(int) $this->id] ?? 0;
    }

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
