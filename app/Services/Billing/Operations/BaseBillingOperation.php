<?php

namespace App\Services\Billing\Operations;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\Billing\DTO\OperationResultDTO;
use App\Services\Billing\Enum\TransactionType;

abstract class BaseBillingOperation
{
    public function calculateAmount(): float
    {
        return $this->client->tariffPrice->license_price +
            $this->client->tariffPrice->tariff_price;
    }

    public function getCurrency(): string
    {
        return $this->client->currency->symbol_code;
    }
}
