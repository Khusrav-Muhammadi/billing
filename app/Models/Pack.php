<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'amount', 'price', 'tariff_id', 'payment_type', 'is_external'];

    protected $casts = [
        'price' => 'decimal:4',
        'amount' => 'integer',
        'tariff_id' => 'integer',
        'is_external' => 'boolean',
    ];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }
}
