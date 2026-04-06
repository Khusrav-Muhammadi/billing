<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentItem extends Model
{
    use HasFactory;

    protected $fillable = ['service_name', 'price', 'payment_id'];

    protected $casts = [
        'price' => 'decimal:4',
    ];
}
