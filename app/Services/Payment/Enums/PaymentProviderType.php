<?php

namespace App\Services\Payment\Enums;

enum PaymentProviderType: string
{
    case ALIF = 'ALIF';
    case OCTOBANK = 'OCTOBANK';
    case INVOICE = 'INVOICE';
}
