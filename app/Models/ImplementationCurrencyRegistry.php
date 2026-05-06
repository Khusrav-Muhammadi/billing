<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImplementationCurrencyRegistry extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'organization_id',
        'commercial_offer_id',
        'partner_id',
        'offer_currency_id',
        'payable_currency_id',
        'base_amount',
        'discount_percent',
        'discount_amount',
        'extra_amount',
        'total_amount',
        'payable_amount',
        'conversion_rate',
        'request_type',
    ];

    protected $casts = [
        'date' => 'datetime',
        'organization_id' => 'integer',
        'commercial_offer_id' => 'integer',
        'partner_id' => 'integer',
        'offer_currency_id' => 'integer',
        'payable_currency_id' => 'integer',
        'base_amount' => 'decimal:4',
        'discount_percent' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'extra_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'payable_amount' => 'decimal:4',
        'conversion_rate' => 'decimal:6',
    ];
}
