<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'currency_id',
        'limited_balance',
        'ai_balance',
        'is_agent_enabled',
        'scheduled_activation_at',
    ];

    protected $casts = [
        'limited_balance' => 'decimal:4',
        'ai_balance' => 'decimal:4',
        'is_agent_enabled' => 'boolean',
        'scheduled_activation_at' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function totalBalance(): float
    {
        return (float) $this->limited_balance + (float) $this->ai_balance;
    }
}
