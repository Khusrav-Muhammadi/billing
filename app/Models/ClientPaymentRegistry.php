<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPaymentRegistry extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_date',
        'organization_id',
        'commercial_offer_id',
        'partner_id',
        'payment_method',
        'account_id',
        'gross_amount',
        'net_amount',
        'tariff_currency',
        'tariff_amount',
        'payment_currency',
        'payment_amount',
        'request_type',
    ];

    protected $casts = [
        'offer_date' => 'date',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tariff_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
    ];
}

