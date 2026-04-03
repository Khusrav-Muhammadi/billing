<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialOfferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'commercial_offer_id',
        'tariff_id',
        'quantity',
        'unit_price',
        'months',
        'discount_percent',
        'partner_percent',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'partner_percent' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function offer()
    {
        return $this->belongsTo(CommercialOffer::class, 'commercial_offer_id');
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }
}
