<?php

namespace App\Repositories\Payment;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceStatus;
use App\Models\Organization;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Repositories\Payment\Contracts\PaymentRepositoryInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentRepository implements PaymentRepositoryInterface
{

    public function createInvoice(array $data)
    {
        $token = config('payments.alif.token');
        $url = config('payments.alif.url');
dd($data);
        $tariff = Tariff::where('name', $data['tariff_name'])->firstOrFail();
        $client = Client::where('sub_domain', $data['sub_domain'])->firstOrFail();

        $invoiceData = $this->prepareInvoiceData($data['organization_id'], $client);
        $invoice = Invoice::create($invoiceData);

        $invoiceItems = $this->prepareInvoiceItems($tariff, $invoice->id);
        InvoiceItem::insert($invoiceItems);

        $response = Http::withHeaders([
            'Token' => $token,
            'Content-Type' => 'application/json',
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
            //            'webhook_url' => 'https://' . $client->sub_domain . '-back.shamcrm.com/api/payment/alif/webhook',
            'webhook_url' => 'https://2cc0-95-142-94-22.ngrok-free.app/api/payment/alif/webhook',

        ];
    }

    private function prepareInvoiceItems(Tariff $tariff, int $invoiceId): array
    {
        return [
            [
                'name' => 'Лицензия для тарифа ' . $tariff->name,
                'spic' => '11201001001000000',
                'amount' => 1,
                'price' => $tariff->price,
                'invoice_id' => $invoiceId,
            ],
            [
                'name' => 'Ежемесячная оплата тарифа ' . $tariff->name,
                'spic' => '11201001001000000',
                'amount' => 1,
                'price' => $tariff->price,
                'invoice_id' => $invoiceId,
            ]
        ];
    }


    public function webhook(Request $request)
    {
        try {
            $invoice = Invoice::where('invoice_id', $request->id)->first();

            if ($request->payment['status'] !== 'SUCCEEDED') {
                return response()->json(['message' => 'Payment not succeeded'], 200);
            }

            DB::transaction(function () use ($invoice, $request) {
                $this->processSuccessfulPayment($invoice, $request->price);
            });

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        } catch (ValidationException $e) {

            return response()->json(['error' => $e->getMessage()], 422);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function processSuccessfulPayment(Invoice $invoice, float $price)
    {
        $successStatus = InvoiceStatus::where('is_success', true)->first();

        $invoice->update(['invoice_status_id' => $successStatus->id]);

        $organization = Organization::findOrFail($invoice->organization_id);
        $organization->increment('balance', $price);

        $client = Client::with(['currency.latestExchangeRate', 'tariff', 'tariffPrice', 'sale'])
            ->where('phone', $invoice->phone)
            ->firstOrFail();

        $currency = $client->currency;
        $exchangeRate = $currency->latestExchangeRate?->kurs ?? 1;

        $amounts = $this->calculateAmounts($client, $price, $currency, $exchangeRate);

        $this->createTransaction($client, $organization, $price, $amounts['accounted_amount']);

        $this->processDemoClient($client, $organization, $amounts);

    }

    private function calculateAmounts(Client $client, float $price, $currency, float $exchangeRate): array
    {
        $isUSD = $currency->symbol_code === 'USD';

        $licenseSum = $client->tariffPrice->license_price ?? 0;
        $tariffSum = $client->tariffPrice->tariff_price ?? 0;

        return [
            'license_sum' => $licenseSum,
            'tariff_sum' => $tariffSum,
            'accounted_amount' => $isUSD ? $price / $exchangeRate : $price,
            'license_accounted' => $isUSD ? $licenseSum / $exchangeRate : $licenseSum,
            'tariff_accounted' => $isUSD ? $tariffSum / $exchangeRate : $tariffSum,
        ];
    }

    private function createTransaction(Client $client, Organization $organization, float $sum, float $accountedAmount)
    {
        Transaction::create([
            'client_id' => $client->id,
            'organization_id' => $organization->id,
            'tariff_id' => $client->tariff?->id,
            'sale_id' => $client->sale?->id,
            'sum' => $sum,
            'type' => 'Пополнение',
            'accounted_amount' => $accountedAmount
        ]);
    }

    private function processDemoClient(Client $client, Organization $organization, array $amounts): void
    {
        $transactions = [];
        $needsUpdate = false;

        if ($client->is_demo) {
            $client->update(['is_demo' => false]);

            $organization->decrement('balance', $amounts['tariff_sum']);
            $organization->decrement('balance', $amounts['license_sum']);

            $transactions = [
                [
                    'sum' => $amounts['tariff_sum'],
                    'accounted_amount' => $amounts['tariff_accounted']
                ],
                [
                    'sum' => $amounts['license_sum'],
                    'accounted_amount' => $amounts['license_accounted']
                ]
            ];

            $needsUpdate = true;
        } else {
            if (!$client->is_active) {
                $client->update(['is_active' => true]);
                $needsUpdate = true;
            }

            if (!$organization->has_access) {
                $organization->update(['has_access' => true]);

                if (!$client->wasChanged('is_demo')) {
                    $transactions = [
                        [
                            'sum' => $amounts['tariff_sum'],
                            'accounted_amount' => $amounts['tariff_accounted']
                        ]
                    ];
                }

                $needsUpdate = true;
            }

        }

        if ($needsUpdate) {
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
}
