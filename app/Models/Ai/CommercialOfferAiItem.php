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
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'partner_percent' => 'decimal:2',
        'original_price' => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    public function commercialOffer(): BelongsTo
    {
        return $this->belongsTo(CommercialOffer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiTariffPlan::class, 'plan_id');
    }
}
