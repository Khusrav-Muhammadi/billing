<?php

namespace App\Services\Response;

class PaymentResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $paymentUrl = null,
        public readonly ?string $downloadUrl = null,
        public readonly ?string $providerInvoiceId = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $metadata = null
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'payment_url' => $this->paymentUrl,
            'download_url' => $this->downloadUrl,
            'provider_invoice_id' => $this->providerInvoiceId,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata
        ];
    }
}
