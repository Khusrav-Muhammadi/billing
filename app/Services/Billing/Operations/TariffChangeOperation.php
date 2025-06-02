<?php

namespace App\Services\Billing\Operations;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\TariffCurrency;
use App\Models\Transaction;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TariffChangeOperation extends BaseBillingOperation
{
    public Client $client;
    public TariffCurrency $newTariff;
    public function __construct(public Organization $organization, public array $operationData)
    {
        $this->client = $organization->client;
        $this->newTariff = TariffCurrency::find($this->operationData['tariff_id']);
    }


    public function getMetadata(): array
    {
        return [
            'description' => "Изменение тарифа {$this->newTariff->tariff->name}",
            'newTariff' => $this->newTariff,
            'currentTarif' => $this->client->tariffPrice->tariff,
            'client_name' => $this->client->name,
            'phone' => $this->client->phone,
            'email' => $this->client->email,
            'subdomain' => $this->client->sub_domain,
            'license_difference' => $this->newTariff->license_price > $this->client->tariffPrice->license_price ? ($this->newTariff->license_price - $this->client->tariffPrice->license_price) : 0,
            'tariff_price' => $this->client->tariffPrice->tariff_price,
            'currency_id' => $this->client->currency_id,
            'months' => $this->operationData["months"]
        ];
    }

    public function execute(): void
    {
        DB::transaction(function () {
            $this->client->update(['is_demo' => false]);
            $invoiceItems = InvoiceItem::query()->where('invoice_id', $this->operationData['invoice_id'])->get();

            foreach ($invoiceItems as $invoiceItem) {
                $this->createTransaction($invoiceItem, TransactionType::CREDIT);
                $this->organization->increment('balance', $invoiceItem->price);
            }
            foreach ($invoiceItems as $invoiceItem) {
                if ($invoiceItem->purpose == TransactionPurpose::LICENSE->value){
                    $this->createTransaction($invoiceItem, TransactionType::DEBIT);
                    $this->organization->decrement('balance', $invoiceItem->price);
                    $this->organization->update(['license_paid' => true]);
                }
                else {
                    $service = new WithdrawalService();
                    $sum = $service->countSum($this->client);
                    $service->handle($this->organization, $sum);
                }
            }
        });
    }
}
