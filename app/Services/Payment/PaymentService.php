<?php

namespace App\Services\Payment;

use App\Models\Invoice;
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
        $operationResult = $this->billingService->calculateOperation(
            $operationType,
            $operationData
        );

        $invoiceDto = new CreateInvoiceDTO(
            amount: $operationResult->amount,
            operationType: $operationType,
            currency: $operationResult->currency,
            metadata: $operationResult->metadata
        );

        return $this->providerFactory
            ->create($provider)
            ->createInvoice($invoiceDto);

    }

    /**
     * Подтверждает платеж и выполняет бизнес-логику
     */
    public function confirmPayment(PaymentOperationType $operationType, string $providerInvoiceId): void
    {
        $this->billingService->executeOperation($operationType, $providerInvoiceId);
    }
}
