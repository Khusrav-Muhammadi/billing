<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'partner_status_id', 'email', 'phone', 'address'];

    public function partnerStatus()
    {
        return $this->belongsTo(PartnerStatus::class, 'partner_status_id');
    }
}
