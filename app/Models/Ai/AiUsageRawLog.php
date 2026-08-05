<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageRawLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'ai_usage_log_id',
        'crm_log_id',
        'model_name',
        'prompt_tokens',
        'prompt_cache_hit_tokens',
        'completion_tokens',
        'calculated_cost',
        'cost_currency_id',
        'price_per_1m_input_snapshot',
        'price_per_1m_output_snapshot',
        'margin_percent_snapshot',
        'processed',
        'created_at',
        'fetched_at',
    ];

    protected $casts = [
        'calculated_cost' => 'decimal:6',
        'price_per_1m_input_snapshot' => 'decimal:6',
        'price_per_1m_output_snapshot' => 'decimal:6',
        'margin_percent_snapshot' => 'decimal:2',
        'processed' => 'boolean',
        'created_at' => 'datetime',
        'fetched_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(AiUsageLog::class, 'ai_usage_log_id');
    }

    public function costCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'cost_currency_id');
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /** Входные токены (включая cache hit). */
    public function inputTokens(): int
    {
        return (int) $this->prompt_tokens + (int) $this->prompt_cache_hit_tokens;
    }

    /** Продажная сумма (то, что списали с клиента). */
    public function sellAmount(): float
    {
        return round((float) $this->calculated_cost, 6);
    }

    /**
     * Себестоимость: sell = cost / (1 - margin/100)
     * → cost = sell * (1 - margin/100).
     */
    public function costAmount(): float
    {
        $margin = (float) ($this->margin_percent_snapshot ?? 0);
        if ($margin < 0) {
            $margin = 0;
        }
        if ($margin >= 100) {
            return 0.0;
        }

        return round((float) $this->calculated_cost * (1 - $margin / 100), 6);
    }
}
