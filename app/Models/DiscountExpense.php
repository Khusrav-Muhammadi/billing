<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_date',
        'client_id',
        'partner_id',
        'tariff_id',
        'service_key',
        'commercial_offer_id',
        'commercial_offer_item_id',
        'discount_amount',
        'original_amount',
        'discount_percent',
        'currency_code',
    ];

    protected $casts = [
        'offer_date' => 'date',
        'discount_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
    ];
}
