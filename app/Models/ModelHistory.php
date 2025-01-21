<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelHistory extends Model
{
    use HasFactory;

    protected $fillable = ['model_id', 'status', 'user_id', 'model_type'];
}
