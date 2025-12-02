<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteApplications extends Model
{
    protected $fillable = ['request_type', 'fio', 'email', 'phone', 'organization', 'region'];
}
