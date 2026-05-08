<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtraUserPriceTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_id',
        'organization_id',
        'currency_id',
        'min_total_users',
        'max_total_users',
        'unit_price',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'tariff_id' => 'integer',
        'organization_id' => 'integer',
        'currency_id' => 'integer',
        'min_total_users' => 'integer',
        'max_total_users' => 'integer',
        'unit_price' => 'decimal:4',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
