<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DayClosing extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'day_closings';

    protected $fillable = ['date', 'doc_number', 'author_id', 'client_amount', 'status'];

    protected $casts = [
        'date' => 'datetime',
        'status' => 'boolean',
        'author_id' => 'integer',
        'client_amount' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DayClosingDetail::class, 'day_closing_id');
    }
}
