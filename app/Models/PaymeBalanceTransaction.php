<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymeBalanceTransaction extends Model
{
    protected $fillable = [
        'payme_id', 'payme_time', 'organization_id', 'account', 'amount_tiyin',
        'currency_id', 'state', 'create_time', 'perform_time', 'cancel_time',
        'reason', 'income_balance_id', 'reversal_balance_id',
    ];

    protected $casts = [
        'payme_time' => 'integer',
        'amount_tiyin' => 'integer',
        'state' => 'integer',
        'create_time' => 'integer',
        'perform_time' => 'integer',
        'cancel_time' => 'integer',
        'reason' => 'integer',
    ];
}
