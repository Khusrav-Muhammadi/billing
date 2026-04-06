<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ConnectedClientServices extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'partner_id',
        'tariff_id',
        'commercial_offer_id',
        'account_id',
        'service_total_amount',
        'status',
        'date',
        'offer_currency_id',
        'payable_currency_id',
        'payable_amount',
    ];

    protected $casts = [
        'status' => 'boolean',
        'date' => 'datetime',
        'service_total_amount' => 'decimal:4',
        'payable_amount' => 'decimal:4',
        'offer_currency_id' => 'integer',
        'payable_currency_id' => 'integer',
    ];

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }

    public function offerCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'offer_currency_id');
    }
}
