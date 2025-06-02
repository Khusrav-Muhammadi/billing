<?php

namespace App\Services\Response;

use App\Services\Billing\Enum\PaymentOperationType;

class WebhookResponse
{
    public function __construct(
        public bool $success,
        public ?string $operationId = null,
        public PaymentOperationType $operationType,
        public ?string $message = null,
        public ?array $providerResponse = null
    ) {}

    public function toArray(): array
    {
        $response = [
            'success' => $this->success,
            'operation_id' => $this->operationId,
            'operation_type' => $this->operationType,
            'message' => $this->message
        ];

        if ($this->providerResponse) {
            $response = array_merge($response, $this->providerResponse);
        }

        return $response;
    }
}
