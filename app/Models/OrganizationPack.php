<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationPack extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'pack_id', 'date'];

    public function pack()
    {
        return $this->belongsTo(Pack::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
