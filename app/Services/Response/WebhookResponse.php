<?php

namespace App\Services\Response;

class WebhookResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $operationId = null,
        public readonly ?string $message = null,
        public readonly ?array $providerResponse = null
    ) {}

    public function toArray(): array
    {
        $response = [
            'success' => $this->success,
            'operation_id' => $this->operationId,
            'message' => $this->message
        ];

        if ($this->providerResponse) {
            $response = array_merge($response, $this->providerResponse);
        }

        return $response;
    }
}
