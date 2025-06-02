<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelHistory extends Model
{
    use HasFactory;
    protected $fillable = ['model_id', 'status', 'user_id', 'model_type'];

    public function changes() :HasMany
    {
        return $this->hasMany(ChangeHistory::class, 'model_history_id', 'id');
    }

    public function model()
    {
        return $this->morphTo();
    }

    public function user() :BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
