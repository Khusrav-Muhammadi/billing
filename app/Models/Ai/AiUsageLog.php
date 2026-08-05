<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiUsageLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'organization_id',
        'currency_id',
        'total_cost',
        'deducted_from_limited',
        'deducted_from_ai_balance',
        'period_start',
        'period_end',
        'created_at',
    ];

    protected $casts = [
        'total_cost' => 'decimal:6',
        'deducted_from_limited' => 'decimal:6',
        'deducted_from_ai_balance' => 'decimal:6',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function rawLogs(): HasMany
    {
        return $this->hasMany(AiUsageRawLog::class, 'ai_usage_log_id')->orderBy('created_at');
    }
}
