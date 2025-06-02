<?php

namespace App\Services\Sale\Enum;

enum SaleApplies :string
{
    case PROGRESSIVE = 'progressive';

    case LICENSE = 'license';
    CASE TARIFF = 'tariff';
}
