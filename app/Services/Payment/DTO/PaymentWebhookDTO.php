<?php

namespace App\Services\Payment\DTO;

use App\Models\Client;

class PaymentWebhookDTO
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly PaymentOperationType $operationType,
        public readonly array $metadata
    ) {}
}
