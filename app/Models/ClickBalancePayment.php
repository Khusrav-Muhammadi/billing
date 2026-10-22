<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickBalancePayment extends Model
{
    protected $fillable = [
        'click_paydoc_id', 'attempt_trans_id', 'paid_paydoc_id', 'organization_id',
        'account', 'amount', 'currency_id', 'status', 'income_balance_id',
        'reversal_balance_id', 'provider_error', 'paid_at', 'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
