<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceStatus;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlifPayProvider implements PaymentProviderInterface
{
    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse
    {
        $invoice = $this->createInvoiceRecord($dto);
        $invoiceItems = $this->prepareInvoiceItems($invoice->id, $dto);

        $response = $this->sendToAlif($invoice, $invoiceItems);

        return new PaymentResponse(
            success: true,
            paymentUrl: $response['id'],
            providerInvoiceId: $response['id']
        );
    }

    private function createInvoiceRecord(CreateInvoiceDTO $dto): Invoice
    {
        return Invoice::create([
            'receipt' => true,
            'organization_id' => $dto->metadata['organization_id'],
            'phone' => $dto->metadata['phone'],
            'timeout' => 86400,
            'meta' => (object)[],
            'invoice_status_id' => InvoiceStatus::where('is_new', true)->first()->id,
            'cancel_url' => $this->generateUrl($dto, 'payment-failed'),
            'redirect_url' => $this->generateUrl($dto, 'payment'),
            'webhook_url' => $this->generateUrl($dto, 'api/payment/webhook/alif'),
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
                invoiceId: $invoiceId
            ),
            $this->makeItem(
                name: "Активация тарифа {$dto->metadata['tariff_name']}",
                price: $dto->metadata['tariff_price'],
                invoiceId: $invoiceId
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
                price: $monthlyPrice * $months,
                invoiceId: $invoiceId
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
                invoiceId: $invoiceId
            );
        }

        $items[] = $this->makeItem(
            name: "Смена тарифа на {$dto->metadata['new_tariff']}",
            price: $dto->metadata['tariff_price'],
            invoiceId: $invoiceId
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
                invoiceId: $invoiceId
            )
        ];
    }

    private function makeItem(string $name, float $price, int $invoiceId): array
    {
        return [
            'name' => $name,
            'spic' => '11201001001000000',
            'amount' => 1,
            'price' => $price,
            'invoice_id' => $invoiceId,
        ];
    }

    private function sendToAlif(Invoice $invoice, array $items): array
    {
        $response = Http::withHeaders([
            'Token' => config('payments.alif.token'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->post(config('payments.alif.url'), [array_merge($invoice->toArray(), $items)]);

        if ($response->failed()) {
            Log::error('Alif API error', ['response' => $response->body()]);
            throw new PaymentException('Failed to create invoice in Alif');
        }

        return $response->json();
    }

    private function generateUrl(CreateInvoiceDTO $dto, string $path): string
    {
        $subdomain = $dto->metadata['subdomain'];
        return "https://{$subdomain}shamcrm.com/{$path}";
    }

    public function handleWebhook(array $data): WebhookResponse
    {
        $this->verifySignature($data);

        $invoice = Invoice::where('invoice_id', $data['id'])->firstOrFail();

        return new WebhookResponse(
            success: $data['payment']['status'] === 'SUCCEEDED',
            operationId: $invoice->id,
            message: $data['payment']['status'],
            providerResponse: ['alif_status' => $data['payment']['status']]
        );
    }

    private function verifySignature(array $data): void
    {
        // Реализация проверки подписи Alif
    }
}
