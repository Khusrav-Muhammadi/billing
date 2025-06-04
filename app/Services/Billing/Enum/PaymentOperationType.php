<?php

namespace App\Services\Billing\Enum;

enum PaymentOperationType: string
{
    case DEMO_TO_LIVE = 'demo_to_live';
    case TARIFF_RENEWAL = 'tariff_renewal';
    case TARIFF_CHANGE = 'tariff_change';
    case ADDON_PURCHASE = 'addon_purchase';
    case ADD_ORGANIZATION = 'add_organization';
}
