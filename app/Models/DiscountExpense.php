<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'client_id',
        'partner_id',
        'tariff_id',
        'discount_amount',
        'original_amount',
        'discount_percent',
        'currency_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'discount_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'currency_id' => 'integer',
    ];
}
