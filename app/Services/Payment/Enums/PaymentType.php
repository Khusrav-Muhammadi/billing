<?php

namespace App\Services\Payment\Enums;

enum PaymentType: string
{
    case ALIF = 'ALIF';
    case OCTOBANK = 'OCTOBANK';
    case INVOICE = 'INVOICE';
}
