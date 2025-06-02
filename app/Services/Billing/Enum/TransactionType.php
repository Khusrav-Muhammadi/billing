<?php

namespace App\Services\Billing\Enum;

enum TransactionType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
}
