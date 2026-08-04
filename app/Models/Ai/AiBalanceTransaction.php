<?php

namespace App\Models\Ai;

use App\Models\Currency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBalanceTransaction extends Model
{
    use HasFactory;

    // Transaction types
    const TYPE_TOPUP             = 'topup';
    const TYPE_PAYMENT           = 'payment';           // subscription payment credited to ai_balance
    const TYPE_MONTHLY_PURCHASE  = 'monthly_purchase';  // deduction from ai_balance to buy monthly limit
    const TYPE_DEDUCTION         = 'deduction';
    const TYPE_OVERDRAFT_COVER   = 'overdraft_cover';
    const TYPE_EXPIRED_PROFIT    = 'expired_profit';
    const TYPE_TARIFF_GRANT      = 'tariff_grant';
    const TYPE_TARIFF_GRANT_PRORATED = 'tariff_grant_prorated';
    const TYPE_DEBT_COVER        = 'debt_cover';
    const TYPE_REVERSAL          = 'reversal';          // clawback on cancel/edit of paid KP

    // Target balances
    const TARGET_LIMITED = 'limited';
    const TARGET_AI_BALANCE = 'ai_balance';

    protected $fillable = [
        'organization_id',
        'currency_id',
        'type',
        'target_balance',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
