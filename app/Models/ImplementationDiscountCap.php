<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImplementationDiscountCap extends Model
{
    use HasFactory;

    /** Типы потолка скидки на внедрение. */
    public const PERIOD_TYPES = [
        'standard' => 'Стандартная',
        'months_12' => '12 месяцев',
        'ai' => 'ИИ',
    ];

    protected $fillable = [
        'tariff_id',
        'period_type',
        'currency_code',
        'max_percent',
        'is_active',
    ];

    protected $casts = [
        'max_percent' => 'decimal:4',
        'is_active' => 'boolean',
        'tariff_id' => 'integer',
    ];
}
