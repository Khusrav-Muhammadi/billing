<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Invoice;
use App\Models\InvoiceStatus;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OctoBankProvider implements PaymentProviderInterface
{
    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse
    {
        $invoice = $this->createInvoiceRecord($dto);
        $requestData = $this->prepareRequestData($dto, $invoice);
        $response = $this->sendRequest($requestData);

        return new PaymentResponse(
            success: $response['error'] === 0,
            paymentUrl: $response['octo_pay_url'] ?? null,
            providerInvoiceId: $response['octo_payment_UUID'] ?? null,
            errorMessage: $response['apiMessageForDevelopers'] ?? null,
            metadata: $response
        );
    }

    private function prepareRequestData(CreateInvoiceDTO $dto, Invoice $invoice): array
    {


        return [
            'octo_shop_id' => config('payments.octo.shop_id'),
            'octo_secret' => config('payments.octo.shop_secret'),
            'shop_transaction_id' => $invoice->invoice_id,
            'auto_capture' => true,
            'init_time' => now()->format('Y-m-d H:i:s'),
            'test' => true,
            'user_data' => [
                'user_id' => $dto->metadata['client_id'],
                'phone' => $dto->metadata['phone'],
                'email' => $dto->metadata['email']
            ],
            'total_sum' => $dto->amount,
            'currency' => $dto->currency,
            'description' => $this->getDescription($dto->operationType),
            'basket' => $this->prepareBasketItems($dto),
            'return_url' => $this->generateReturnUrl($dto),
            'notify_url' => $this->generateWebhookUrl($dto),
            'language' => 'ru',
            'ttl' => 1440
        ];
    }

    private function generateTransactionId(): string
    {
        return Str::uuid()->toString();
    }

    private function prepareBasketItems(CreateInvoiceDTO $dto): array
    {
        return match ($dto->operationType) {
            PaymentOperationType::DEMO_TO_LIVE => [
                $this->makeBasketItem(
                    name: "Лицензия ({$dto->metadata['tariff_name']})",
                    price: $dto->metadata['license_price'],
                    quantity: 1
                ),
                $this->makeBasketItem(
                    name: "Тариф ({$dto->metadata['tariff_name']})",
                    price: $dto->metadata['tariff_price'],
                    quantity: 1
                )
            ],
            PaymentOperationType::TARIFF_RENEWAL => [
                $this->makeBasketItem(
                    name: "Продление тарифа ({$dto->metadata['tariff_name']})",
                    price: $dto->amount,
                    quantity: 1
                )
            ],
            default => [
                $this->makeBasketItem(
                    name: $this->getDescription($dto->operationType),
                    price: $dto->amount,
                    quantity: 1
                )
            ]
        };
    }

    private function makeBasketItem(string $name, float $price, int $quantity): array
    {
        return [
            'position_desc' => $name,
            'count' => $quantity,
            'price' => $price,
            'spic' => '11201001001000000' // Код для фискализации
        ];
    }

    private function sendRequest(array $data): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->post(config('payments.octo.url'), $data);

        if ($response->failed()) {
            throw new PaymentException("Octo API error: " . $response->body());
        }

        return $response->json();
    }

    public function handleWebhook(array $data): WebhookResponse
    {
        return new WebhookResponse(
            success: $data['status'] === 'completed',
            operationId: $data['shop_transaction_id'],
            message: $data['status'],
            providerResponse: $data
        );
    }

    private function generateReturnUrl(CreateInvoiceDTO $dto): string
    {
        return "https://{$dto->metadata['subdomain']}shamcrm.com/payment/return?" . http_build_query([
                'transaction_id' => $dto->metadata['transaction_id']
            ]);
    }

    private function generateWebhookUrl(CreateInvoiceDTO $dto): string
    {
        return "https://{$dto->metadata['subdomain']}-back.shamcrm.com/api/payment/webhook/octo";
    }

    private function getDescription(PaymentOperationType $type): string
    {
        return match($type) {
            PaymentOperationType::DEMO_TO_LIVE => 'Активация аккаунта',
            PaymentOperationType::TARIFF_RENEWAL => 'Продление тарифа',
            PaymentOperationType::TARIFF_CHANGE => 'Смена тарифного плана',
            PaymentOperationType::ADDON_PURCHASE => 'Покупка доп. пакета'
        };
    }
    private function createInvoiceRecord(CreateInvoiceDTO $dto): Invoice
    {
        return Invoice::create([
            'receipt' => true,
            'organization_id' => $dto->metadata['organization_id'],
            'phone' => $dto->metadata['phone'],
            'timeout' => 86400,
            'meta' => (object)[],
            'invoice_status_id' => InvoiceStatus::query()->where('is_new', true)->first()->id,
            'invoice_id' => Str::uuid()->toString(),
        ]);
    }
}
