<?php

namespace App\Services\Billing\Operations;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\Billing\DTO\OperationResultDTO;
use App\Services\Billing\Enum\TransactionType;

abstract class BaseBillingOperation
{
    protected Client $client;
    protected ?Organization $organization;
    protected float $licenseSum = 0;
    protected float $tariffSum = 0;

    public function __construct(Client $client, ?Organization $organization = null)
    {
        $this->client = $client;
        $this->organization = $organization;
    }

    abstract public function execute(): OperationResultDTO;

    protected function calculateLicenseCost(): float
    {
        return $this->client->tariffPrice->license_price ?? 0;
    }

    protected function calculateTariffCost(int $months = 1): float
    {
        return ($this->client->tariffPrice->tariff_price ?? 0) * $months;
    }

    protected function convertToUSD(float $amount, string $currency, float $exchangeRate): float
    {
        return $currency !== 'USD' ? $amount / $exchangeRate : $amount;
    }

    protected function createTransaction(float $sum, TransactionType $transactionType, float $accountedAmount): void
    {
        Transaction::create([
            'client_id' => $this->client->id,
            'organization_id' => $this->organization?->id,
            'tariff_id' => $this->client->tariff?->id,
            'sale_id' => $this->client->sale?->id,
            'sum' => $sum,
            'type' => $transactionType,
            'accounted_amount' => $accountedAmount
        ]);
    }
}
