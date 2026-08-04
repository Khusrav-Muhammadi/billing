<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /**
     * Money in ai_balance reserved for future prepaid months
     * (from next calendar month through subscription expires_at).
     * Must NOT be spent on mid-month token overage.
     */
    public function reservedWalletAmount(?AiSubscription $subscription = null): float
    {
        $subscription ??= AiSubscription::query()
            ->where('organization_id', $this->organization_id)
            ->active()
            ->where('expires_at', '>=', now())
            ->with('plan.currentPrice')
            ->orderByDesc('id')
            ->first();

        if (! $subscription?->plan) {
            return 0.0;
        }

        // monthlyLimit() бросает, если нет актуальной цены — лучше ошибка, чем нулевой резерв.
        $monthly = $subscription->plan->monthlyLimit();

        $now = Carbon::now('Asia/Dushanbe');
        $nextMonthStart = $now->copy()->addMonthNoOverflow()->startOfMonth()->startOfDay();
        $expiresAt = Carbon::parse($subscription->expires_at)->timezone('Asia/Dushanbe');

        if ($expiresAt->lt($nextMonthStart)) {
            return 0.0;
        }

        $cursor = $nextMonthStart->copy();
        $months = 0;
        while ($cursor->lte($expiresAt) && $months < 120) {
            $months++;
            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return round($months * $monthly, 4);
    }

    /**
     * Free part of ai_balance (top-ups / leftovers) that may be used
     * when limited_balance is exhausted during the month.
     */
    public function spendableWalletAmount(?AiSubscription $subscription = null): float
    {
        $reserved = $this->reservedWalletAmount($subscription);

        return round(max(0.0, (float) $this->ai_balance - $reserved), 4);
    }

    /**
     * How much can still pay for tokens right now:
     * positive limited + spendable wallet (not reserved months).
     */
    public function availableForUsageAmount(?AiSubscription $subscription = null): float
    {
        $limited = max(0.0, (float) $this->limited_balance);
        $spendable = $this->spendableWalletAmount($subscription);

        return round($limited + $spendable, 4);
    }
}
