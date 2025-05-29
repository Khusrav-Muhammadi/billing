<?php

namespace App\Services\Payment;

use App\Services\Billing\BillingService;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Payment\Factory\PaymentProviderFactory;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;

class PaymentService
{
    public function __construct(
        private BillingService $billingService,
        private PaymentProviderFactory $providerFactory
    ) {}

    public function initiatePayment(
        PaymentOperationType $operationType,
        array $operationData,
        string $provider
    ): PaymentResponse {
        $operationResult = $this->billingService->executeOperation(
            $operationType,
            $operationData
        );

        $invoiceDto = new CreateInvoiceDTO(
            amount: $operationResult->amount,
            operationType: $operationType,
            currency: $operationResult->currency,
            metadata: $operationResult->metadata
        );

        $provider = $this->providerFactory->create($provider);

        return $provider->createInvoice($invoiceDto);
    }

    public function handleWebhook(string $providerSlug, array $data): WebhookResponse
    {
        $provider = $this->providerFactory->create($providerSlug);
        $response = $provider->handleWebhook($data);

        if ($response->success) {
            $this->billingService->confirmOperation(
                $response->operationId
            );
        }

        return $response;
    }
}
