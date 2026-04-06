<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPaymentRegistry extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'organization_id',
        'commercial_offer_id',
        'partner_id',
        'payment_method',
        'account_id',
        'gross_amount',
        'net_amount',
        'tariff_currency_id',
        'tariff_amount',
        'payment_currency_id',
        'payment_amount',
        'request_type',
    ];

    protected $casts = [
        'date' => 'datetime',
        'gross_amount' => 'decimal:4',
        'net_amount' => 'decimal:4',
        'tariff_amount' => 'decimal:4',
        'payment_amount' => 'decimal:4',
        'tariff_currency_id' => 'integer',
        'payment_currency_id' => 'integer',
    ];
}
