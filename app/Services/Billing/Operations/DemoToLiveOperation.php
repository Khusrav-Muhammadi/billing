<?php

namespace App\Services\Billing\Operations;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\TariffCurrency;
use App\Models\Transaction;
use App\Services\Billing\Enum\TransactionType;
use App\Services\Payment\Enums\TransactionPurpose;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\DB;

class DemoToLiveOperation extends BaseBillingOperation
{
    public Client $client;
    public TariffCurrency $newTariff;

    public function __construct(
        private Organization $organization,
        private array       $operationData,
    )
    {
        $this->client = $this->organization->client;
        $tariffId = Invoice::query()->where('id', $this->operationData['invoice_id'])->first()->tariff_id;
       $this->newTariff = TariffCurrency::find($tariffId);
       // $this->newTariff = TariffCurrency::find($this->client->tariff_id);
    }

    public function calculateAmount(): float
    {
        return $this->newTariff->license_price +
            $this->newTariff->tariff_price;
    }

    public function getMetadata(): array
    {
        return [
            'description' => "Активация тарифа {$this->client->tariffPrice->tariff->name}",
            'client_name' => $this->client->name,
            'tariff_name' => $this->client->tariffPrice->tariff->name,
            'phone' => $this->client->phone,
            'email' => $this->client->email,
            'subdomain' => $this->client->sub_domain,
            'license_price' => $this->newTariff->license_price,
            'tariff_price' => $this->newTariff->tariff_price,
            'currency_id' => $this->client->currency_id,
            'months' => $this->operationData["months"],
        ];
    }

    public function execute(): void
    {
        DB::transaction(function () {
            $invoice = Invoice::find($this->operationData['invoice_id']);
            $this->client->update([
                'is_demo' => false,
                'tariff_id' => $invoice->tariff_id
            ]);
            $invoiceItems = InvoiceItem::query()->where('invoice_id', $invoice->id)->get();

            foreach ($invoiceItems as $invoiceItem) {
                $this->createTransaction($invoiceItem, TransactionType::CREDIT);
                $this->organization->increment('balance', $invoiceItem->price);
            }

            foreach ($invoiceItems as $invoiceItem) {
                if ($invoiceItem->purpose == TransactionPurpose::LICENSE->value) {
                    $this->createTransaction($invoiceItem, TransactionType::DEBIT);
                    $this->organization->decrement('balance', $invoiceItem->price);
                    $this->organization->update(['license_paid' => true]);
                    $this->organization->increment('sum_paid_for_license', $invoiceItem->price);

                } else {
                    $service = new WithdrawalService();
                    $sum = $service->countSum($this->client);
                    $service->handle($this->organization, $sum);
                }
            }
        });
    }

    private function createTransaction(InvoiceItem $invoiceItem, TransactionType $transactionType): void
    {
        $isUSD = $this->getCurrency() === 'USD';
        if (!$isUSD) {
            $exchangeRate = $this->client->currency->latestExchangeRate?->kurs ?? 1;
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
            'accounted_amount' => $isUSD ? $invoiceItem->price : $invoiceItem->price / $exchangeRate,
            'sale_id' => $invoiceItem->sale_id
        ]);
    }
}
