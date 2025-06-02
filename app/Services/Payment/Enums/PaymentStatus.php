<?php

namespace App\Services\Payment\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILURE = 'failure';
}
