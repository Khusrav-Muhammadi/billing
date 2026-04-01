<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'commercial_offer_id',
        'sum',
        'currency',
        'type',
    ];

    protected $casts = [
        'sum' => 'decimal:2',
    ];
}

