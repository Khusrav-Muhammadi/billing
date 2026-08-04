<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTariffPlanPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'months',
        'discount_percent',
        'price_total',
        'valid_from',
        'valid_to',
        'created_by',
    ];

    protected $casts = [
        'months'           => 'integer',
        'discount_percent' => 'decimal:2',
        'price_total'      => 'decimal:2',
        'valid_from'       => 'date',
        'valid_to'         => 'date',
    ];

    public function plan()
    {
        return $this->belongsTo(AiTariffPlan::class, 'plan_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('valid_to');
    }

    /**
     * SCD Type 2: закрыть текущую скидку и создать новую.
     */
    public static function updateDiscount(
        int   $planId,
        int   $months,
        float $discountPercent,
        float $priceTotal,
        int   $userId
    ): self {
        self::query()
            ->where('plan_id', $planId)
            ->where('months', $months)
            ->whereNull('valid_to')
            ->update(['valid_to' => now()->subDay()->toDateString()]);

        return self::query()->create([
            'plan_id'          => $planId,
            'months'           => $months,
            'discount_percent' => $discountPercent,
            'price_total'      => $priceTotal,
            'valid_from'       => now()->toDateString(),
            'valid_to'         => null,
            'created_by'       => $userId,
        ]);
    }
}
