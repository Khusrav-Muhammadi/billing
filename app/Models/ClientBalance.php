<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'date' => 'datetime',
        'sum' => 'decimal:2',
        'currency_id' => 'integer',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
