<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'user_count',
        'project_count',
        'is_tariff',
        'is_extra_user',
        'parent_tariff_id',
        'currency_id',
        'sale',
        'end_date',
        'can_increase',
    ];

    protected $casts = [
        'end_date' => 'date',
        'can_increase' => 'bool',
    ];

    public function currencies()
    {
        return $this->hasMany(TariffCurrency::class);
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function includedServices()
    {
        return $this->belongsToMany(Tariff::class, 'tariff_included_services', 'tariff_id', 'service_id')
            ->withPivot(['quantity'])
            ->withTimestamps();
    }
}
