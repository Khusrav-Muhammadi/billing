<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerRequest extends Model
{
    use HasFactory;

    protected $fillable = ['partner_id', 'client_type', 'name', 'phone', 'email', 'address', 'organization', 'is_demo', 'tariff_id', 'date', 'request_status'];
}
