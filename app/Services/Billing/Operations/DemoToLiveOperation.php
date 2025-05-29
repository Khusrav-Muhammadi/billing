<?php

namespace App\Services\Billing\Operations;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\Billing\DTO\OperationResultDTO;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Billing\Enum\TransactionType;
use Illuminate\Support\Facades\DB;

class DemoToLiveOperation
{
    public function __construct(
        private Client $client,
        private Organization $organization
    ) {}

    public function calculateAmount(): float
    {
        return $this->client->tariffPrice->license_price
            + $this->client->tariffPrice->tariff_price;
    }

    public function getCurrency(): string
    {
        return $this->client->currency->symbol_code;
    }

    public function getMetadata(): array
    {
        return [
            'description' => "Активация тарифа {$this->client->tariff->name}",
            'client_name' => $this->client->name,
            'tariff_name' => $this->client->tariff->name,
            'phone' => $this->client->phone,
            'email' => $this->client->email,
            'subdomain' => $this->client->sub_domain,
            'license_price' => $this->client->tariffPrice->license_price,
            'tariff_price' => $this->client->tariffPrice->tariff_price,
            'currency_id' => $this->client->currency_id
        ];
    }

    /**
     * Выполняется ТОЛЬКО после успешной оплаты
     */
    public function execute(): void
    {
        DB::transaction(function () {

            $this->client->update(['is_demo' => false]);
            $total = $this->calculateAmount();
            $this->organization->increment('balance', $price);
            $this->organization->decrement('balance', $total);

            Transaction::create([
                'client_id' => $this->client->id,
                'organization_id' => $this->organization->id,
                'type' => TransactionType::DEBIT,
                'amount' => $total,
                'currency' => $this->getCurrency(),
                'status' => 'completed'
            ]);
        });
    }
}
