<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name','INN','phone','address', 'client_id', 'has_access', 'business_type_id', 'reject_cause', 'balance', 'license_paid', 'sum_paid_for_license',
        'legal_name', 'legal_address', 'director'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function packs()
    {
        return $this->hasMany(OrganizationPack::class, 'organization_id');
    }

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function history(): MorphMany
    {
        return $this->morphMany('App\Models\ModelHistory', 'model');
    }
}
