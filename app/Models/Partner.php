<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'company', 'email', 'phone', 'address', 'manager_id'];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
