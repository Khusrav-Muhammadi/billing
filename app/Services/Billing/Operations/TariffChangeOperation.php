<?php

namespace App\Services\Billing\Operations;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\TariffCurrency;
use App\Models\Transaction;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TariffChangeOperation
{
    public function countDifference(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        $organization = Organization::find($data['organization_id']);
        $newTariff = TariffCurrency::find($data['tariff_id']);
        $lastTariff = TariffCurrency::find($client->tariff_id);

        $licenseDifference = $newTariff->license_price > $lastTariff->license_price ? ($newTariff->license_price - $lastTariff->license_price) : 0;
        $tariffPrice = $newTariff->tariff_price * $data['month'];

        $difference = $organization->balance - ($licenseDifference + $tariffPrice);

        $invoicePayment = '';

        if ($difference < 0) {
            $invoicePayment = $this->createInvoice($client, -$difference, $organization->id);
        }

        return [
            'organization_balance' => $organization->balance,
            'license_difference' => $licenseDifference,
            'tariff_price' => $tariffPrice,
            'invoice_payment' => $invoicePayment
        ];
    }

    public function changeTariff(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();
        $newTariff = TariffCurrency::find($data['tariff_id']);
        $lastTariff = TariffCurrency::find($client->tariff_id);

        $tariffPrice = $newTariff->tariff_price * $data['month'];

        $organizations = $client->organizations;

        $currency = $client->currency;
        $exchangeRate = $currency->latestExchangeRate?->kurs ?? 1;

        if ($lastTariff->license_price < $newTariff->license_price) {
            $difference = $newTariff->license_price - $lastTariff->license_price;

            $amounts = $this->calculateAmounts($difference, $currency, $exchangeRate);

            foreach ($organizations as $organization) {
                $organization->decrement('balance', $difference);
                $transactions = [
                    [
                        'sum' => $difference,
                        'accounted_amount' => $amounts['accounted_amount']
                    ]
                ];
                $this->createTransactions($client, $organization, $transactions);
            }
        }

        $service = new WithdrawalService();
        $tariffSum = $service->countSum($client);
        $amounts = $this->calculateAmounts($tariffSum, $currency, $exchangeRate);

        foreach ($organizations as $organization) {
            $organization->decrement('balance', $tariffPrice);
            $transactions = [
                [
                    'sum' => $tariffSum,
                    'accounted_amount' => $amounts['accounted_amount']
                ]
            ];
            $this->createTransactions($client, $organization, $transactions);
        }
    }

    private function createTransactions(Client $client, Organization $organization, array $transactions): void
    {
        foreach ($transactions as $transaction) {
            if ($transaction['sum'] > 0) {
                Transaction::create([
                    'client_id' => $client->id,
                    'organization_id' => $organization->id,
                    'tariff_id' => $client->tariff?->id,
                    'sale_id' => $client->sale?->id,
                    'sum' => $transaction['sum'],
                    'type' => 'Снятие',
                    'accounted_amount' => $transaction['accounted_amount']
                ]);
            }
        }
    }

    private function calculateAmounts(float $price, $currency, float $exchangeRate): array
    {
        $isUSD = $currency->symbol_code != 'USD';

        return [
            'accounted_amount' => $isUSD ? $price / $exchangeRate : $price,
        ];
    }

    public function createInvoice(Client $client, int $price, int $organizationId)
    {
        $token = config('payments.alif.token');
        $url = config('payments.alif.url');

        $invoiceData = $this->prepareInvoiceData($organizationId, $client);
        $invoice = Invoice::create($invoiceData);

        $invoiceItems = $this->prepareInvoiceItems($price, $invoice->id);
        InvoiceItem::insert($invoiceItems);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Token' => $token,
            'Accept' => 'application/json'
        ])->post($url, array_merge($invoiceData, ['items' => $invoiceItems]));

        if ($response->failed()) {
            Log::error('Alif invoice creation failed', ['response' => $response->body()]);
            throw new \Exception('Ошибка при создании счета в Alif');
        }

        $res = $response->json();

        $invoice->update(['invoice_id' => $res['id']]);

        return config('payments.alif.payment_page') . $res['id'];
    }

    private function prepareInvoiceData(int $organizationId, Client $client): array
    {
        return [
            'receipt' => true,
            'organization_id' => $organizationId,
            'phone' => $client->phone,
            'timeout' => 86400,
            'meta' => (object)[],
            'invoice_status_id' => 1,
            'cancel_url' => "https://shamcrm.com/payment-failed?subdomain={$client->sub_domain}",
            'redirect_url' => "https://{$client->sub_domain}shamcrm.com/payment",
//            'webhook_url' => 'https://' . $client->sub_domain . '-back.shamcrm.com/api/payment/alif/webhook/change-tariff',
            'webhook_url' => 'https://357b-95-142-94-22.ngrok-free.app/api/payment/alif/webhook/change-tariff',
        ];
    }

    private function prepareInvoiceItems($price, int $invoiceId): array
    {
        return [
            [
                'name' => 'Изменение тарифа',
                'spic' => '11201001001000000',
                'amount' => 1,
                'price' => $price,
                'invoice_id' => $invoiceId,
            ],
        ];
    }
}
