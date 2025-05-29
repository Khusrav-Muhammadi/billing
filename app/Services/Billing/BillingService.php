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

class BillingService
{
    /**
     * Рассчитывает параметры операции БЕЗ выполнения бизнес-логики
     * Используется для создания счета на оплату
     */
    public function calculateOperation(
        PaymentOperationType $operationType,
        array                $operationData
    ): OperationResultDTO
    {
        $operation = $this->createOperationInstance($operationType, $operationData);

        return new OperationResultDTO(
            amount: $operation->calculateAmount(),
            currency: $operation->getCurrency(),
            operationType: $operationType,
            metadata: array_merge($operation->getMetadata(), [
                'operation_type' => $operationType->value,
                'operation_data' => $operationData
            ]),
        );
    }

    /**
     * Выполняет бизнес-логику операции ПОСЛЕ успешной оплаты
     */
    public function executeOperation(
        PaymentOperationType $operationType,
        int                  $invoice_id
    ): void
    {

        $operation = $this->updateInvoiceStatus($invoice_id, $operationType);
        $operation->execute();
    }

    /**
     * Создает экземпляр операции
     */
    private function createOperationInstance(
        PaymentOperationType $operationType,
        array                $operationData
    )
    {
        $organization = Organization::find($operationData["organization_id"]);
        $client = $organization->client;


        return match ($operationType) {
            PaymentOperationType::DEMO_TO_LIVE => new DemoToLiveOperation($client, $organization),
            PaymentOperationType::ADDON_PURCHASE => new AddOnPurchaseOperation(
                Client::findOrFail($operationData['client_id']),
                Organization::findOrFail($operationData['organization_id']),
                $operationData['addon_id'],
                $operationData['quantity'] ?? 1
            ),
            default => throw new \InvalidArgumentException("Unsupported operation: " . $operationType->value)
        };
    }


    private function updateInvoiceStatus(int $invoice_id, $operationType)
    {
        $invoice = Invoice::find($invoice_id);
        $organization = Organization::find($invoice->organization_id);

        $invoice->status = PaymentStatus::SUCCESS;
        $invoice->save();

        return $this->createOperationInstance($operationType, ['organization_id' => $organization->id]);
    }

    /**
     * Рассчитывает ежедневное списание для клиента
     */
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
