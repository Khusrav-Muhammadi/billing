<?php

namespace App\Services\Billing\Operations;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\TariffCurrency;
use App\Models\Transaction;
use App\Services\Billing\Enum\TransactionType;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TariffRenewalOperation extends BaseBillingOperation
{
    public Client $client;
    public TariffCurrency $newTariff;

    public function __construct(
        private Organization $organization,
        private array        $operationData,
    )
    {
        $this->client = $this->organization->client;
        $this->newTariff = $this->resolveNewTariff();
    }

    public function calculateAmount(): float
    {
        return $this->newTariff->tariff_price;
    }

    public function getMetadata(): array
    {
        return [
            'description' => "Активация тарифа {$this->newTariff->tariff->name}",
            'client_name' => $this->client->name,
            'tariff_name' => $this->newTariff->tariff->name,
            'phone' => $this->client->phone,
            'email' => $this->client->email,
            'subdomain' => $this->client->sub_domain,
            'tariff_price' => $this->newTariff->tariff_price,
            'currency_id' => $this->client->country?->currency_id,
            'months' => $this->operationData["months"],
        ];
    }

    public function execute(): void
    {
        DB::transaction(function () {
            $invoiceItems = InvoiceItem::query()->where('invoice_id', $this->operationData['invoice_id'])->get();

            foreach ($invoiceItems as $invoiceItem) {
                $this->createTransaction($invoiceItem, TransactionType::CREDIT);

                $this->organization->balance += $invoiceItem->price;
                $this->organization->save();
            }

            $service = new WithdrawalService();
            $sum = $service->countSum($this->client);
            $service->handle($this->organization, $sum);

        });
    }

    private function createTransaction(InvoiceItem $invoiceItem, TransactionType $transactionType): void
    {
        $isUSD = $this->getCurrency() === 'USD';
        if (!$isUSD) {
            $exchangeRate = $this->client->country?->currency?->latestExchangeRate?->kurs ?? 1;
        }
        Transaction::create([
            'client_id' => $this->client->id,
            'tariff_id' => $this->client->tariff_id,
            'organization_id' => $this->organization->id,
            'type' => $transactionType,
            'sum' => $invoiceItem->price,
            'currency' => $this->getCurrency(),
            'purpose' => $invoiceItem->purpose,
            'provider' => $transactionType == TransactionType::DEBIT ? 'manual' : $invoiceItem->invoice->provider,
            'accounted_amount' => $isUSD ? $invoiceItem->price : $invoiceItem->price / $exchangeRate
        ]);
    }

    private function resolveNewTariff(): TariffCurrency
    {
        $currencyId = (int) ($this->client->country?->currency_id ?? 0) ?: null;
        $tariffName = $this->operationData['tariff_name'] ?? null;

        $candidateIds = [];

        if (!empty($this->operationData['tariff_id'])) {
            $candidateIds[] = (int) $this->operationData['tariff_id'];
        }

        if (!empty($this->operationData['invoice_id'])) {
            $invoiceTariffId = Invoice::query()
                ->whereKey($this->operationData['invoice_id'])
                ->value('tariff_id');

            if ($invoiceTariffId) {
                $candidateIds[] = (int) $invoiceTariffId;
            }
        }

        if ($this->client->tariff_id) {
            $candidateIds[] = (int) $this->client->tariff_id;
        }

        foreach (array_unique($candidateIds) as $id) {
            $tariff = TariffCurrency::resolveById($id, $currencyId, $tariffName);
            if ($tariff) {
                return $tariff;
            }
        }

        throw new InvalidArgumentException('Не найден тариф для продления.');
    }
}
