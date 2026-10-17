<?php

namespace App\Models;

use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ClientBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'organization_id',
        'sum',
        'currency_id',
        'type',
        'source',
    ];

    protected $casts = [
        'date' => 'datetime',
        'sum' => 'decimal:4',
        'currency_id' => 'integer',
        'source' => PaymentType::class,
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
