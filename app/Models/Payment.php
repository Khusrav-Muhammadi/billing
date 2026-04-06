<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'sum', 'payment_type'];

    protected $casts = [
        'sum' => 'decimal:4',
    ];

    public function paymentItems()
    {
        return $this->hasMany(PaymentItem::class);
    }
}
