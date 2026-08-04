<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTariffPlanPrice extends Model
{
    protected $fillable = [
        'plan_id',
        'currency_id',
        'price_monthly',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'start_date'    => 'date',
        'end_date'      => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiTariffPlan::class, 'plan_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Текущая (активная) цена: start_date <= today AND (end_date IS NULL OR end_date >= today) */
    public function scopeCurrent($query)
    {
        $today = now()->toDateString();

        return $query->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '9999-12-31')
                    ->orWhere('end_date', '>=', $today);
            })
            ->orderByDesc('start_date');
    }
}
