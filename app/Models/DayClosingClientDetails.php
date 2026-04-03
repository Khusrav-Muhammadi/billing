<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DayClosingClientDetails extends Model
{
    use HasFactory;

    protected $table = 'day_closing_client_details';

    protected $fillable = [
        'day_closing_details_id',
        'client_id',
        'tariff_id',
        'monthly_sum',
        'daily_sum',
    ];

    protected $casts = [
        'day_closing_details_id' => 'integer',
        'client_id' => 'integer',
        'tariff_id' => 'integer',
        'monthly_sum' => 'decimal:2',
        'daily_sum' => 'decimal:2',
    ];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(DayClosingDetail::class, 'day_closing_details_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class, 'tariff_id');
    }
}
