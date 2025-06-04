<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price', 'user_count', 'project_count'];

    public function currencies()
    {
        return $this->hasMany(TariffCurrency::class);
    }
}
