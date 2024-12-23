<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name','phone','front_sub_domain','back_sub_domain','business_type_id', 'INN', 'address', 'balance', 'tariff_id'];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

}
