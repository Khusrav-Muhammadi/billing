<?php

namespace App\Models\Ai;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiImplementationPrice extends Model
{
    protected $fillable = [
        'plan_id',
        'currency_id',
        'sum',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'sum' => 'decimal:2',
        'plan_id' => 'integer',
        'currency_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AiTariffPlan::class, 'plan_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
