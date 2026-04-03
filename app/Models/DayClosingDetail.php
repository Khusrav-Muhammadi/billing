<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayClosingDetail extends Model
{
    use HasFactory;

    protected $table = 'day_closing_details';

    public $timestamps = false;

    protected $fillable = [
        'day_closing_id',
        'organization_id',
        'currency_id',
        'balance_before_accrual',
        'balance_after_accrual',
        'status_after_accrual',
    ];

    protected $casts = [
        'day_closing_id' => 'integer',
        'organization_id' => 'integer',
        'currency_id' => 'integer',
        'balance_before_accrual' => 'decimal:2',
        'balance_after_accrual' => 'decimal:2',
        'status_after_accrual' => 'boolean',
    ];

    public function dayClosing(): BelongsTo
    {
        return $this->belongsTo(DayClosing::class, 'day_closing_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function serviceDetails(): HasMany
    {
        return $this->hasMany(DayClosingClientDetails::class, 'day_closing_details_id');
    }
}

