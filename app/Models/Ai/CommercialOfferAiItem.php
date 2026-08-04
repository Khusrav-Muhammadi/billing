<?php

namespace App\Models\Ai;

use App\Models\CommercialOffer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialOfferAiItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'commercial_offer_id',
        'plan_id',
        'period_months',
        'unit_price',
        'discount_percent',
        'partner_percent',
        'original_price',
        'total_price',
        'balance_topup',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'partner_percent' => 'decimal:2',
        'original_price' => 'decimal:4',
        'total_price' => 'decimal:4',
        'balance_topup' => 'decimal:4',
    ];

    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiTariffPlan::class, 'plan_id');
    }

    /** Сумма к оплате по AI-блоку: период + запас на баланс */
    public function chargedTotal(): float
    {
        return round((float) $this->total_price + (float) $this->balance_topup, 4);
    }
}
