<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagePrice extends Model
{
    use HasFactory;

    protected $fillable = ['pack_id', 'tariff_id', 'currency_id', 'one_time_price', 'monthly_price'];
}
