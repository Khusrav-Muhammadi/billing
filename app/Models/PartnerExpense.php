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
        'date',
        'tariff_id',
        'partner_amount',
        'original_amount',
        'partner_percent',
        'currency_id',
        'request_type',
    ];

    protected $casts = [
        'date' => 'datetime',
        'partner_amount' => 'decimal:4',
        'original_amount' => 'decimal:4',
        'partner_percent' => 'decimal:2',
        'currency_id' => 'integer',
    ];
}
