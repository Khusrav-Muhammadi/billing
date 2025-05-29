<?php

namespace App\Services\Billing\DTO;

use App\Services\Billing\Enum\PaymentOperationType;

class OperationResultDTO
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly PaymentOperationType $operationType,
        public readonly array $metadata = [],
        public readonly ?string $description = null
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'operation_type' => $this->operationType->value,
            'metadata' => $this->metadata,
            'description' => $this->description
        ];
    }
}
