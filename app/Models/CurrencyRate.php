<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency_id',
        'quote_currency_id',
        'rate',
        'rate_date',
    ];

    protected $casts = [
        'rate_date' => 'date',
    ];

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id');
    }
}
