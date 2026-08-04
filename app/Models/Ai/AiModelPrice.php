<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelPrice extends Model
{
    protected $fillable = [
        'ai_model_id',
        'currency_id',
        'price_per_1m_input',
        'price_per_1m_output',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'price_per_1m_input'  => 'decimal:6',
        'price_per_1m_output' => 'decimal:6',
        'start_date'          => 'date',
        'end_date'            => 'date',
    ];

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
