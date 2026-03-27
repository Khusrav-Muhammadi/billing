<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerProcent extends Model
{
    use HasFactory;

    protected $fillable = ['partner_id', 'date', 'procent_from_tariff', 'procent_from_pack'];

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
