<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'sum',
        'payment_type',
        'transaction_id',
        'provider_status',
        'paid_at',
        'webhook_payload',
    ];

    protected $casts = [
        'sum' => 'decimal:4',
        'paid_at' => 'datetime',
        'webhook_payload' => 'array',
    ];

    public function paymentItems()
    {
        return $this->hasMany(PaymentItem::class);
    }
}
