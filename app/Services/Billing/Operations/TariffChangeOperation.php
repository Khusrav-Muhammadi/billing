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
use Illuminate\Support\Facades\Log;

class TariffChangeOperation extends BaseBillingOperation
{
    public Client $client;
    public TariffCurrency $newTariff;

    public function __construct(public Organization $organization, public array $operationData)
    {
        $this->client = $organization->client;

        if (isset($this->operationData['invoice_id'])) {
            $invoice = Invoice::query()->where('id', $this->operationData['invoice_id'])->first();
            $this->newTariff = TariffCurrency::find($invoice->tariff_id);
        }
        else {
            $this->newTariff = TariffCurrency::find($this->operationData['tariff_id']);
        }
    }

    public function getMetadata(): array
    {
        return [
            'description' => "Изменение тарифа {$this->newTariff->tariff->name}",
            'newTariff' => $this->newTariff,
            'currentTariff' => $this->client->tariffPrice->tariff,
            'client_name' => $this->client->name,
            'phone' => $this->client->phone,
            'email' => $this->client->email,
            'subdomain' => $this->client->sub_domain,
            'license_difference' => $this->newTariff->license_price > $this->organization->sum_paid_for_license? ($this->newTariff->license_price - $this->organization->sum_paid_for_license) : 0,
            'tariff_price' => $this->newTariff->tariff_price,
            'currency_id' => $this->client->currency_id,
            'months' => $this->operationData["months"],
            'organization_balance' => $this->organization->balance
        ];
    }

    public function execute(): void
    {
        DB::transaction(function () {
            $invoice = Invoice::find($this->operationData['invoice_id']);
            $invoiceItem = InvoiceItem::where('invoice_id', $invoice->id)->first();

            $this->client->update(['tariff_id' => $invoice->tariff_id]);

            DB::transaction(function () use ($invoiceItem) {
                $this->processSuccessfulPayment($invoiceItem);
            });

            return response()->json(['message' => 'Webhook processed successfully'], 200);
        });
    }

    private function processSuccessfulPayment(InvoiceItem $invoiceItem)
    {
        $invoice = $invoiceItem->invoice;
        $tariffPrice = TariffCurrency::find($invoice->tariff_id);

        $this->organization->increment('balance', $invoiceItem->price);
Log::error($invoiceItem->price);
        $this->createTransaction($invoiceItem, TransactionType::CREDIT);

        $licenseDifference = $tariffPrice->license_price - $this->organization->sum_paid_for_license;
        $licenseDifference = max($licenseDifference, 0);

        $this->organization->decrement('balance', $licenseDifference);
        $this->organization->increment('sum_paid_for_license', $licenseDifference);

        $this->createTransaction($invoiceItem, TransactionType::DEBIT);

        $service = new WithdrawalService();
        $sum = $service->countSum($this->organization->client);
        Log::error($sum);
        $service->handle($this->organization, $sum);


        //Todo  надо обновить тариф в срм
    }

    private function createTransaction(InvoiceItem $invoiceItem, TransactionType $transactionType): void
    {
        $isUSD = $this->getCurrency() === 'USD';
        if(!$isUSD) {
            $exchangeRate = $this->client->currency->latestExchangeRate?->kurs ?? 1;
        }

        Transaction::create([
            'client_id' => $this->client->id,
            'organization_id' => $this->organization->id,
            'tariff_id' => $this->client->tariff_id,
            'type' => $transactionType,
            'sum' => $invoiceItem->price,
            'currency' => $this->getCurrency(),
            'status' => 'completed',
            'purpose' =>  $invoiceItem->purpose,
            'provider' => $transactionType == TransactionType::DEBIT ? 'manual' : $invoiceItem->invoice->provider,
            'accounted_amount' => $isUSD ? $invoiceItem->price : $invoiceItem->price / $exchangeRate
        ]);
    }

}
