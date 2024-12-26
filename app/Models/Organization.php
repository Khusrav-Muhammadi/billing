<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name','INN','phone','address', 'client_id', 'has_access'];


    public function client()
    {
        return $this
            ->belongsTo(Client::class);
    }

    public function packs()
    {
        return $this->hasMany(OrganizationPack::class, 'organization_id');
    }
}
