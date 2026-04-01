<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'client_id',
        'offer_date',
        'service_type',
        'service_id',
        'service_key',
        'commercial_offer_id',
        'commercial_offer_item_id',
        'partner_amount',
        'original_amount',
        'partner_percent',
        'currency_code',
        'request_type',
    ];

    protected $casts = [
        'offer_date' => 'date',
        'partner_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'partner_percent' => 'decimal:2',
    ];
}
