<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerRequest extends Model
{
    use HasFactory;

    protected $fillable = ['partner_id', 'client_type', 'name', 'phone', 'email', 'address', 'is_demo', 'tariff_id', 'date', 'request_status', 'sub_domain', 'reject_cause'];

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }
}
