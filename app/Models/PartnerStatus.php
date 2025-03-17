<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'organization_connect_percent', 'tariff_price_percent', 'connect_amount', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
