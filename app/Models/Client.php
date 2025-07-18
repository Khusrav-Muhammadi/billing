<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Client extends Model
{
    use HasFactory, Filterable;

    public bool $disableObserver = false;

    protected $fillable = ['name','phone', 'sub_domain', 'INN', 'address', 'balance', 'tariff_id', 'reject_cause', 'sale_id',
        'is_active', 'is_demo', 'last_activity', 'email', 'contact_person', 'client_type', 'partner_id', 'city_id', 'nfr', 'country_id', 'currency_id', 'manager_id'];

    public function activeSales()
    {
        return $this->belongsToMany(Sale::class, 'client_sale')
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }
    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'last_activity' => 'datetime'
    ];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }

    public function tariffPrice()
    {
        return $this->belongsTo(TariffCurrency::class, 'tariff_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    public function partner() :BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager() :BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function city() : BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function country() : BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function history(): MorphMany
    {
        return $this->morphMany('App\Models\ModelHistory', 'model');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function clientSales()
    {
        return $this->hasMany(ClientSale::class);
    }

}
