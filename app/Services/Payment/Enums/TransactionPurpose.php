<?php

namespace App\Services\Payment\Enums;

enum TransactionPurpose: string
{
    case TARIFF = 'tariff';

    case LICENSE = 'license';

    case ADDON_PACKAGE = 'addon_package';
<<<<<<< HEAD

    case CHANGE_TARIFF = 'change_tariff';
=======
    case DISCOUNT = 'discount';
>>>>>>> 2ced203319337fea1879e89c491a6c7b81743ae0
}
