<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'user_count', 'project_count', 'is_tariff', 'currency_id', 'sale'];

    public function currencies()
    {
        return $this->hasMany(TariffCurrency::class);
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }
}
