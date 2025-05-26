<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffCurrency extends Model
{
    use HasFactory;

    protected $fillable = ['currency_id', 'tariff_id', 'price', 'license_price'];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }
}
