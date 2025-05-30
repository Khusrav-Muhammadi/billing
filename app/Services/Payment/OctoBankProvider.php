<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceStatus;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Payment\Enums\PaymentProviderType;
use App\Services\Payment\Enums\PaymentStatus;
use App\Services\Payment\Enums\TransactionPurpose;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OctoBankProvider implements PaymentProviderInterface
{
    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse
    {
        $invoice = $this->createInvoiceRecord($dto);

        $invoiceItems = $this->prepareInvoiceItems($invoice->id, $dto);

        foreach ($invoiceItems as $invoiceItem) {
            InvoiceItem::query()->create($invoiceItem);
        }

        $response = $this->sendToOctoBank($dto, $invoiceItems, $invoice);
        $invoice->payment_provider_id = $response['octo_payment_UUID'];
        $invoice->additional_data = json_encode([
            'shop_transaction_id' => $response['shop_transaction_id'],
            'octo_payment_UUID' => $response['octo_payment_UUID']
        ]);
        $invoice->save();

        return new PaymentResponse(
            success: true,
            paymentUrl: $response['octo_pay_url'],
            providerInvoiceId: $response['octo_payment_UUID']
        );
    }

    private function createInvoiceRecord(CreateInvoiceDTO $dto): Invoice
    {
        return Invoice::create([
            'organization_id' => $dto->metadata['operation_data']['organization_id'],
            'status' => PaymentStatus::PENDING,
            'currency_id' => $dto->metadata['currency_id'],
            'email' => $dto->metadata['email'],
            'total_amount' => $dto->metadata['license_price'] + ($dto->metadata['tariff_price'] * $dto->metadata['operation_data']['months']),
            'provider' => PaymentProviderType::OCTOBANK,
            'additional_data' => json_encode([]),
            'operation_type' => $dto->operationType
        ]);
    }

    private function prepareInvoiceItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        return match ($dto->operationType) {
            PaymentOperationType::DEMO_TO_LIVE => $this->demoToLiveItems($invoiceId, $dto),
            PaymentOperationType::TARIFF_RENEWAL => $this->tariffRenewalItems($invoiceId, $dto),
            PaymentOperationType::TARIFF_CHANGE => $this->tariffChangeItems($invoiceId, $dto),
            PaymentOperationType::ADDON_PURCHASE => $this->addonItems($invoiceId, $dto),
            default => throw new \InvalidArgumentException('Unsupported operation type')
        };
    }

    private function demoToLiveItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        return [
            $this->makeItem(
                name: "Лицензия для тарифа {$dto->metadata['tariff_name']}",
                price: $dto->metadata['license_price'],
                months: $dto->metadata['operation_data']['months'],
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::LICENSE,
                count: 1
            ),
            $this->makeItem(
                name: "Активация тарифа {$dto->metadata['tariff_name']}",
                price: $dto->metadata['tariff_price'],
                months: $dto->metadata['operation_data']['months'],
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::TARIFF,
                count: $dto->metadata['operation_data']['months']
            )
        ];
    }

    private function tariffRenewalItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $months = $dto->metadata['months'];
        $monthlyPrice = $dto->metadata['monthly_price'];

        return [
            $this->makeItem(
                name: "Продление тарифа {$dto->metadata['tariff_name']} на {$months} мес.",
                price: $monthlyPrice,
                months: $months,
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::TARIFF,
                count: $months
            )
        ];
    }

    private function tariffChangeItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $items = [];

        if ($dto->metadata['license_diff'] > 0) {
            $items[] = $this->makeItem(
                name: "Разница лицензии ({$dto->metadata['old_tariff']} → {$dto->metadata['new_tariff']})",
                price: $dto->metadata['license_diff'],
                months: $dto->metadata['operation_data']['months'],
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::LICENSE,
                count: 1
            );
        }

        $items[] = $this->makeItem(
            name: "Смена тарифа на {$dto->metadata['new_tariff']}",
            price: $dto->metadata['tariff_price'],
            months: $dto->metadata['operation_data']['months'],
            invoiceId: $invoiceId,
            purpose: TransactionPurpose::TARIFF,
            count: $dto->metadata['operation_data']['months']
        );

        return $items;
    }

    private function addonItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        return [
            $this->makeItem(
                name: $dto->metadata['addon_type'] === 'one-time'
                    ? "Пакет {$dto->metadata['addon_name']}"
                    : "Пакет {$dto->metadata['addon_name']} (ежемесячно)",
                price: $dto->metadata['addon_price'],
                months: $dto->metadata['operation_data']['months'],
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::ADDON_PACKAGE,
                count: $dto->metadata['addon_type'] === 'one-time' ? 1 : $dto->metadata['operation_data']['months']
            )
        ];
    }

    private function makeItem(string $name, float $price, int $months, int $invoiceId, TransactionPurpose $purpose, int $count, int $sale_id = null): array
    {
        return [
            'name' => $name,
            'amount' => $count,
            'price' => $price,
            'invoice_id' => $invoiceId,
            'spic' => '11201001001000000',
            'purpose' => $purpose,
            'sale_id' => $sale_id,
            'position_desc' => $name,
            'count' => $count
        ];
    }

    private function sendToOctoBank(CreateInvoiceDTO $dto, array $items, Invoice $invoice): array
    {
        $basket = array_map(function ($item) {
            return [
                'position_desc' => $item['position_desc'],
                'count' => $item['count'],
                'price' => $item['price'],
                'spic' => $item['spic']
            ];
        }, $items);

        $shopTransactionId = $this->generateShopTransactionId($invoice->id);

        $payload = [
            'octo_shop_id' => (int) config('payments.octobank.shop_id'),
            'octo_secret' => config('payments.octobank.shop_secret'),
            'shop_transaction_id' => $shopTransactionId,
            'auto_capture' => true,
            'test' => true,
            'init_time' => now()->format('Y-m-d H:i:s'),
            'user_data' => [
                'user_id' => $dto->metadata['operation_data']['organization_id'],
                'phone' => $dto->metadata['phone'],
                'email' => $dto->metadata['email']
            ],
            'total_sum' => (float) $invoice->total_amount,
            'currency' => 'USD',
            'description' => $this->getPaymentDescription($dto->operationType, $dto->metadata),
            'basket' => $basket,
            'return_url' => $this->generateReturnUrl($dto),
            'notify_url' => config('payments.octobank.webhook_url'),
            'language' => config('payments.octobank.language', 'ru'),
            'ttl' => config('payments.octobank.ttl', 1440)
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->post(config('payments.octobank.url'), $payload);

        if ($response->failed()) {
            Log::error('OctoBank API error', [
                'response' => $response->body(),
                'payload' => $payload
            ]);
            throw new PaymentException('Failed to create invoice in OctoBank');
        }

        $responseData = $response->json();

        if ($responseData['error'] !== 0) {
            Log::error('OctoBank API returned error', [
                'error' => $responseData['error'],
                'message' => $responseData['apiMessageForDevelopers'] ?? 'Unknown error'
            ]);
            throw new PaymentException('OctoBank returned error: ' . ($responseData['apiMessageForDevelopers'] ?? 'Unknown error'));
        }

        return $responseData['data'] ?? $responseData;
    }

    private function generateShopTransactionId(int $invoiceId): string
    {
        return $invoiceId;
    }


    private function getPaymentDescription(PaymentOperationType $operationType, array $metadata): string
    {
        return match ($operationType) {
            PaymentOperationType::DEMO_TO_LIVE => "Активация лицензии и тарифа {$metadata['tariff_name']}",
            PaymentOperationType::TARIFF_RENEWAL => "Продление тарифа {$metadata['tariff_name']}",
            PaymentOperationType::TARIFF_CHANGE => "Смена тарифа на {$metadata['new_tariff']}",
            PaymentOperationType::ADDON_PURCHASE => "Покупка пакета {$metadata['addon_name']}",
            default => 'Оплата услуг ShamCRM'
        };
    }

    private function generateReturnUrl(CreateInvoiceDTO $dto): string
    {
        $subdomain = $dto->metadata['subdomain'];
        return "https://{$subdomain}.shamcrm.com/payment";
    }

    public function handleWebhook(array $data): WebhookResponse
    {

        $invoice = Invoice::where('payment_provider_id', $data['octo_payment_UUID'])->firstOrFail();

        $success = $data['status'] === 'succeeded';

        $providerResponse = [
            'octobank_status' => $data['status'],
            'total_sum' => $data['total_sum'] ?? null,
            'transfer_sum' => $data['transfer_sum'] ?? null,
            'refunded_sum' => $data['refunded_sum'] ?? null,
            'card_country' => $data['card_country'] ?? null,
            'masked_pan' => $data['maskedPan'] ?? null,
            'rrn' => $data['rrn'] ?? null,
            'risk_level' => $data['riskLevel'] ?? null,
            'payed_time' => $data['payed_time'] ?? null,
            'card_type' => $data['card_type'] ?? null
        ];

        return new WebhookResponse(
            success: $success,
            operationId: $invoice->id,
            operationType: PaymentOperationType::from($invoice->operation_type),
            message: $data['status'],
            providerResponse: $providerResponse
        );
    }

    public function getPaymentStatus(string $octoPaymentUUID): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->post(config('payments.octobank.status_url'), [
            'octo_shop_id' => (int) config('payments.octobank.shop_id'),
            'octo_secret' => config('payments.octobank.secret'),
            'octo_payment_UUID' => $octoPaymentUUID
        ]);

        if ($response->failed()) {
            throw new PaymentException('Failed to get payment status from OctoBank');
        }

        return $response->json();
    }
}
