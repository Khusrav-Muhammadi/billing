<?php

namespace App\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Partner extends Model
{
    use HasFactory, Filterable;

    protected $fillable = ['name', 'partner_status_id', 'email', 'phone', 'address'];

    public function partnerStatus()
    {
        return $this->belongsTo(PartnerStatus::class, 'partner_status_id');
    }

    public function history(): MorphMany
    {
        return $this->morphMany('App\Models\ModelHistory', 'model');
    }
}
