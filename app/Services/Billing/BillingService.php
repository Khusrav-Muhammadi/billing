<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Services\Billing\DTO\OperationResultDTO;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Billing\Operations\AddonPurchaseOperation;
use App\Services\Billing\Operations\DemoToLiveOperation;
use App\Services\Billing\Operations\TariffChangeOperation;
use App\Services\Payment\Enums\PaymentStatus;
use App\Services\Sale\SaleService;

class BillingService
{
    public function __construct(public SaleService $saleService)
    {
    }

    public function calculateOperation(
        PaymentOperationType $operationType,
        array                $operationData
    ): OperationResultDTO
    {
        $operation = $this->createOperationInstance($operationType, $operationData);

        $metadata = $operation->getMetadata();

        $activeSales = $this->saleService->getActiveSales();

        $this->saleService->applyDiscounts(
            $activeSales,
            $operationData,
            $metadata
        );

        return new OperationResultDTO(
            amount: $operation->calculateAmount(),
            currency: $operation->getCurrency(),
            operationType: $operationType,
            metadata: array_merge($metadata, [
                'operation_type' => $operationType->value,
                'operation_data' => $operationData
            ]),
        );
    }

    public function executeOperation(
        PaymentOperationType $operationType,
        int                  $invoice_id
    ): void
    {

        $operation = $this->updateInvoiceStatus($invoice_id, $operationType);
        $operation->execute();
    }

    private function createOperationInstance(
        PaymentOperationType $operationType,
        array                $operationData
    )
    {
        $organization = Organization::find($operationData["organization_id"]);

        return match ($operationType) {
            PaymentOperationType::DEMO_TO_LIVE => new DemoToLiveOperation($organization, $operationData),
            PaymentOperationType::TARIFF_CHANGE => new TariffChangeOperation($organization, $operationData),
            PaymentOperationType::ADDON_PURCHASE => new AddonPurchaseOperation(),
        };
    }

    private function updateInvoiceStatus(int $invoice_id, $operationType)
    {
        $invoice = Invoice::find($invoice_id);
        $organization = Organization::find($invoice->organization_id);

        $invoice->status = PaymentStatus::SUCCESS;
        $invoice->save();

        return $this->createOperationInstance($operationType, ['organization_id' => $organization->id, 'invoice_id' => $invoice_id]);
    }

    public function calculateDailyPayment(Client $client): float
    {
        if (!$client->tariff) return 0;

        $daysInMonth = now()->daysInMonth;
        $dailyPayment = $client->tariff->price / $daysInMonth;

        $packsDailyPayment = $client->organizations->sum(function ($org) use ($daysInMonth) {
            return $org->packs->sum(fn($pack) => ($pack->price / $daysInMonth));
        });

        return max(0, $dailyPayment + $packsDailyPayment - $this->calculateDiscount($client, $daysInMonth));
    }

    private function calculateDiscount(Client $client, int $daysInMonth): float
    {
        if (!$client->sale) return 0;

        return $client->sale->sale_type === 'procent'
            ? ($client->tariff->price * $client->sale->amount) / (100 * $daysInMonth)
            : $client->sale->amount / $daysInMonth;
    }
}
