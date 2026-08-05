<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AiModelPrice extends Model
{
    protected $fillable = [
        'ai_model_id',
        'currency_id',
        'cost_per_1m_input',
        'cost_per_1m_cache',
        'cost_per_1m_output',
        'margin_percent',
        'price_per_1m_input',
        'price_per_1m_cache',
        'price_per_1m_output',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'cost_per_1m_input' => 'decimal:6',
        'cost_per_1m_cache' => 'decimal:6',
        'cost_per_1m_output' => 'decimal:6',
        'margin_percent' => 'decimal:2',
        'price_per_1m_input' => 'decimal:6',
        'price_per_1m_cache' => 'decimal:6',
        'price_per_1m_output' => 'decimal:6',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (AiModelPrice $price): void {
            $price->applyMarginToSellPrices();
        });
    }

    /**
     * sell = cost / (1 - margin/100)
     * margin 90% → себестоимость = 10% от продажной цены.
     */
    public function applyMarginToSellPrices(): void
    {
        $margin = (float) $this->margin_percent;
        if ($margin < 0 || $margin >= 100) {
            throw new RuntimeException('margin_percent must be >= 0 and < 100.');
        }

        $divisor = 1 - ($margin / 100);
        if ($divisor <= 0) {
            throw new RuntimeException('Invalid margin divisor.');
        }

        $this->price_per_1m_input = round((float) $this->cost_per_1m_input / $divisor, 6);
        $this->price_per_1m_cache = round((float) $this->cost_per_1m_cache / $divisor, 6);
        $this->price_per_1m_output = round((float) $this->cost_per_1m_output / $divisor, 6);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Текущая цена: start_date <= today AND (end_date IS NULL OR open-ended OR end_date >= today) */
    public function scopeCurrent($query, ?string $onDate = null)
    {
        $day = $onDate ?: now()->toDateString();

        return $query->where('start_date', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '9999-12-31')
                    ->orWhere('end_date', '>=', $day);
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    public function isCurrent(?string $onDate = null): bool
    {
        $day = $onDate ?: now()->toDateString();
        $start = $this->start_date?->toDateString();
        $end = $this->end_date?->toDateString();

        if (! $start || $start > $day) {
            return false;
        }

        return $end === null || $end === '9999-12-31' || $end >= $day;
    }
}
