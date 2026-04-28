<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImplementationDiscountCap extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_id',
        'period_type',
        'max_percent',
        'is_active',
    ];

    protected $casts = [
        'max_percent' => 'decimal:4',
        'is_active' => 'boolean',
        'tariff_id' => 'integer',
    ];
}
