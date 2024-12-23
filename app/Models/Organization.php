<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name','INN','phone','address', 'client_id', 'has_access', 'sale_id', 'tariff_id',];


    public function client()
    {
        return $this
            ->belongsTo(Client::class);
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function packs()
    {
        return $this->hasMany(OrganizationPack::class, 'organization_id');
    }
}
