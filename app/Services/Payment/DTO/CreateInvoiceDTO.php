<?php

namespace App\Services\Payment\DTO;

use App\Services\Billing\Enum\PaymentOperationType;

class CreateInvoiceDTO
{
    public function __construct(
        public readonly float $amount,
        public readonly PaymentOperationType $operationType,
        public readonly string $currency,
        public readonly array $metadata
    ) {}
}
