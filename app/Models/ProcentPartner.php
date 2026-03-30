<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcentPartner extends Model
{
    use HasFactory;

    protected $table = 'procent_partners';

    protected $fillable = [
        'partner_id',
        'procent_from_tariff',
        'procent_from_pack',
    ];

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}

