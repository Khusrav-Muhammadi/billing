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

    protected $fillable = ['name','phone', 'sub_domain','business_type_id', 'INN', 'address',
        'balance', 'tariff_id', 'sale_id', 'is_active', 'is_demo', 'last_activity', 'email', 'contact_person', 'client_type', 'partner_id', 'city_id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'last_activity' => 'datetime'
    ];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
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
        return $this->belongsTo(Partner::class);
    }

    public function city() : BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function history(): MorphMany
    {
        return $this->morphMany('App\Models\ModelHistory', 'model');
    }

}
