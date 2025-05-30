<?php

namespace App\Services\Payment\Enums;

enum TransactionPurpose: string
{
    case TARIFF = 'tariff';
    case LICENSE = 'license';

    case ADDON_PACKAGE = 'addon_package';
}
