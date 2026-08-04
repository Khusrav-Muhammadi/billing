<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiTokenPricing extends Model
{
    use HasFactory;

    protected $table = 'ai_token_pricing';

    protected $fillable = [
        'provider',
        'model_name',
        'cost_currency_id',
        'cost_per_1m_input',
        'cost_per_1m_output',
        'margin_percent',
        'price_currency_id',
        'price_per_1m_input',
        'price_per_1m_output',
        'effective_from',
        'effective_to',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'cost_per_1m_input' => 'decimal:6',
        'cost_per_1m_output' => 'decimal:6',
        'margin_percent' => 'decimal:2',
        'price_per_1m_input' => 'decimal:6',
        'price_per_1m_output' => 'decimal:6',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function costCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'cost_currency_id');
    }

    public function priceCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'price_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * SCD Type 2: close the current price row and create a new one.
     * Direct update of pricing fields is forbidden — use this method instead.
     */
    public static function updatePrice(array $newData, int $userId): self
    {
        return DB::transaction(function () use ($newData, $userId): self {
            self::query()
                ->where('model_name', $newData['model_name'])
                ->whereNull('effective_to')
                ->update(['effective_to' => now()]);

            $marginDivisor = 1 - ((float) $newData['margin_percent'] / 100);

            $newData['price_per_1m_input'] = round(
                (float) $newData['cost_per_1m_input'] / $marginDivisor,
                6
            );
            $newData['price_per_1m_output'] = round(
                (float) $newData['cost_per_1m_output'] / $marginDivisor,
                6
            );
            $newData['effective_from'] = now();
            $newData['effective_to'] = null;
            $newData['created_by'] = $userId;

            return self::query()->create($newData);
        });
    }

    /**
     * Scope: current (active) price for a model.
     */
    public function scopeCurrent($query, string $modelName)
    {
        return $query->where('model_name', $modelName)->whereNull('effective_to');
    }
}
