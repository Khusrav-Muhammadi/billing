<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Payment\Enums\TransactionPurpose;
use App\Services\Payment\Enums\PaymentProviderType;
use App\Services\Payment\Enums\PaymentStatus;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function Symfony\Component\Translation\t;

class AlifPayProvider implements PaymentProviderInterface
{
    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse
    {
        $invoice = $this->createInvoiceRecord($dto);

        $invoiceItems = $this->prepareInvoiceItems($invoice->id, $dto);

        foreach ($invoiceItems as $invoiceItem) {
            InvoiceItem::query()->create($invoiceItem);
        }

        $items = collect($invoiceItems)->map(function ($item) {
            $item['price'] = intval($item['price'] * 100);
            return $item;
        })->toArray();
        $response = $this->sendToAlif($dto, $items);
        $invoice->payment_provider_id = $response['id'];
        $invoice->save();

        return new PaymentResponse(
            success: true,
            paymentUrl: config('payments.alif.payment_page') . $response['id'],
            providerInvoiceId: $response['id']
        );
    }

    private function createInvoiceRecord(CreateInvoiceDTO $dto): Invoice
    {
        return Invoice::create([
            'organization_id' => $dto->metadata['operation_data']['organization_id'],
            'status' => PaymentStatus::PENDING,
            'currency_id' => $dto->metadata['currency_id'],
            'email' => $dto->metadata['email'],
            'total_amount' => $this->calculateTotalSum($dto),
            'provider' => PaymentProviderType::ALIF,
            'additional_data' => json_encode([]),
            'operation_type' => $dto->operationType,
            'tariff_id' => $dto->metadata['operation_data']['tariff_id'],
            'months' => $dto->metadata['operation_data']['months']
        ]);
    }

    private function prepareInvoiceItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        return match ($dto->operationType) {
            PaymentOperationType::DEMO_TO_LIVE => $this->demoToLiveItems($invoiceId, $dto),
            PaymentOperationType::TARIFF_RENEWAL => $this->tariffRenewalItems($invoiceId, $dto),
            PaymentOperationType::TARIFF_CHANGE => $this->tariffChangeItems($invoiceId, $dto),
            PaymentOperationType::ADDON_PURCHASE => $this->addonItems($invoiceId, $dto),
            PaymentOperationType::ADD_ORGANIZATION => $this->demoToLiveItems($invoiceId, $dto),
            default => throw new \InvalidArgumentException('Unsupported operation type')
        };
    }

    private function demoToLiveItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $items = [];
        if (($dto->metadata['license_price'] ?? 0) > 0) {
            $originalLicensePrice = $dto->metadata['license_price'];
//            $licensePrice = $this->applyDiscount($originalLicensePrice, 'license', $dto->metadata);
            $licenseSaleId = $dto->metadata['discounts']['license']['sale_id'] ?? null;

            if ($originalLicensePrice > 0) {
                $items[] = $this->makeItem(
                    name: "Внедрение для тарифа {$dto->metadata['tariff_name']}",
                    price: $originalLicensePrice,
                    months: $dto->metadata['operation_data']['months'],
                    invoiceId: $invoiceId,
                    purpose: TransactionPurpose::LICENSE,
                    count: 1,
                    sale_id: $licenseSaleId
                );
            }
        }

        $originalTariffPrice = $dto->metadata['tariff_price'];
        $tariffPrice = $this->applyDiscount($originalTariffPrice, 'tariff', $dto->metadata);
        $tariffSaleId = $dto->metadata['discounts']['tariff']['sale_id'] ?? null;

        $items[] = $this->makeItem(
            name: "Активация тарифа {$dto->metadata['tariff_name']}",
            price: $tariffPrice * $dto->metadata['operation_data']['months'], // Умножаем цену на месяцы здесь
            months: $dto->metadata['operation_data']['months'],
            invoiceId: $invoiceId,
            purpose: TransactionPurpose::TARIFF,
            count: 1, // Исправлено: count должен быть 1, а цена уже умножена на месяцы
            sale_id: $tariffSaleId
        );

        return $items;
    }

    private function tariffRenewalItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $months = $dto->metadata['months'];

        $originalMonthlyPrice = $dto->metadata['tariff_price'];
        $monthlyPrice = $this->applyDiscount($originalMonthlyPrice, 'tariff', $dto->metadata);
        $tariffSaleId = $dto->metadata['discounts']['tariff']['sale_id'] ?? null;

        return [
            $this->makeItem(
                name: "Продление тарифа {$dto->metadata['tariff_name']} на {$months} мес.",
                price: $monthlyPrice * $dto->metadata['operation_data']['months'], // Умножаем цену на месяцы здесь
                months: $dto->metadata['operation_data']['months'],
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::EXTEND_TARIFF,
                count: 1,
                sale_id: $tariffSaleId
            )
        ];
    }

    private function tariffChangeItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $items = [];

        $tariffSaleId = $dto->metadata['discounts']['tariff']['sale_id'] ?? null;
        $licenseDifference = $this->applyDiscount($dto->metadata['license_difference'], 'license', $dto->metadata);
        $tariffPrice = $this->applyDiscount($dto->metadata['newTariff']->tariff_price, 'tariff', $dto->metadata);

        $items[] = $this->makeItem(
            name: "Изменение тарифа ({$dto->metadata['currentTariff']->name} → {$dto->metadata['newTariff']->tariff->name})",
            price: abs(($dto->metadata['organization_balance'] - ($licenseDifference + ($tariffPrice * $dto->metadata['months'])))),
            months: 1, // Исправлено: как в OctoBankProvider
            invoiceId: $invoiceId,
            purpose: TransactionPurpose::CHANGE_TARIFF,
            count: 1,
            sale_id: $tariffSaleId
        );

        return $items;
    }

    private function addonItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $originalAddonPrice = $dto->metadata['addon_price'];
        $addonPrice = $this->applyDiscount($originalAddonPrice, 'addon', $dto->metadata);
        $addonSaleId = $dto->metadata['discounts']['addon']['sale_id'] ?? null;

        return [
            $this->makeItem(
                name: $dto->metadata['addon_type'] === 'one-time'
                    ? "Пакет {$dto->metadata['addon_name']}"
                    : "Пакет {$dto->metadata['addon_name']} (ежемесячно)",
                price: $addonPrice,
                months: $dto->metadata['operation_data']['months'],
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::ADDON_PACKAGE,
                count: $dto->metadata['addon_type'] === 'one-time' ? 1 : $dto->metadata['operation_data']['months'],
                sale_id: $addonSaleId
            )
        ];
    }

    private function applyDiscount(float $originalPrice, string $discountType, array $metadata): float
    {
        if (!isset($metadata['discounts'][$discountType])) {
            return $originalPrice;
        }

        $discount = $metadata['discounts'][$discountType];
        $percent = floatval($discount['percent']);

        if ($discountType === 'tariff' && isset($discount['months_required'])) {
            $months = $metadata['months'] ?? $metadata['operation_data']['months'] ?? 1;
            if ($months < $discount['months_required']) {
                return $originalPrice;
            }
        }

        $discountAmount = ($originalPrice * $percent) / 100;
        return max(0, $originalPrice - $discountAmount);
    }

    // Исправлено: убрана автоматическая логика умножения на месяцы
    private function makeItem(string $name, float $price, int $months, int $invoiceId, TransactionPurpose $purpose, int $count, int $sale_id = null): array
    {
        return [
            'name' => $name,
            'amount' => 1,
            'price' => $price, // Просто передаем цену как есть
            'invoice_id' => $invoiceId,
            'spic' => '11201001001000000',
            'purpose' => $purpose,
            'sale_id' => $sale_id
        ];
    }

    private function sendToAlif(CreateInvoiceDTO $DTO, array $items): array
    {
        $response = Http::withHeaders([
            'Token' => config('payments.alif.token'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->post(config('payments.alif.url'), [
            'items' => $items,
            'cancel_url' => "https://shamcrm.com/payment-failed?subdomain={$DTO->metadata['subdomain']}",
//            'redirect_url' => "https://{$DTO->metadata['subdomain']}.shamcrm.com/payment",
            'redirect_url' => "https://fingroupcrm.shamcrm.com/payment",
            'webhook_url' => 'https://billing-back.shamcrm.com/api/payment/webhook/ALIF',
//            'webhook_url' => 'https://2328-95-142-94-22.ngrok-free.app/api/payment/webhook/ALIF',
            'meta' => (object)[],
            'receipt' => true,
            'phone' => ltrim($DTO->metadata['phone'], '+'),
            'timeout' => 86400,
        ]);

        if ($response->failed()) {
            Log::error('Alif API error', ['response' => $response->body()]);
            throw new PaymentException('Failed to create invoice in Alif');
        }

        return $response->json();
    }

    private function generateUrl(CreateInvoiceDTO $dto, string $path): string
    {
        $subdomain = $dto->metadata['subdomain'];
        return "https://{$subdomain}.shamcrm.com/{$path}";
    }

    private function calculateTotalSum(CreateInvoiceDTO $dto): float
    {
        return match ($dto->operationType) {
            PaymentOperationType::DEMO_TO_LIVE => $this->calculateDemoToLiveTotal($dto),
            PaymentOperationType::TARIFF_RENEWAL => $this->calculateTariffRenewalTotal($dto),
            PaymentOperationType::TARIFF_CHANGE => $this->calculateTariffChangeTotal($dto),
            PaymentOperationType::ADDON_PURCHASE => $this->calculateAddonTotal($dto), // Добавлено как в OctoBankProvider
            PaymentOperationType::ADD_ORGANIZATION => $this->calculateDemoToLiveTotal($dto),
        };
    }

    private function calculateDemoToLiveTotal(CreateInvoiceDTO $dto): float
    {
        $total = 0;

        if (($dto->metadata['license_price'] ?? 0) > 0) {
//            $licensePrice = $this->applyDiscount($dto->metadata['license_price'], 'license', $dto->metadata);
            $total += $dto->metadata['license_price'];
        }

        $tariffPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);
        $total += $tariffPrice * $dto->metadata['operation_data']['months'];

        return $total;
    }

    private function calculateTariffRenewalTotal(CreateInvoiceDTO $dto): float
    {
        $monthlyPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);
        return $monthlyPrice * $dto->metadata['months'];
    }

    private function calculateAddonTotal(CreateInvoiceDTO $dto): float
    {
        return $this->applyDiscount($dto->metadata['addon_price'], 'addon', $dto->metadata);
    }

    private function calculateTariffChangeTotal(CreateInvoiceDTO $dto): float
    {
        $total = 0;

        if (($dto->metadata['license_difference'] ?? 0) > 0) {
            $licensePrice = $this->applyDiscount($dto->metadata['license_difference'], 'license', $dto->metadata);
            $total += $licensePrice;
        }

        $tariffPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);
        $total += $tariffPrice * $dto->metadata['operation_data']['months'];

        return $total;
    }

    public function handleWebhook(array $data): WebhookResponse
    {
        $invoice = Invoice::where('payment_provider_id', $data['id'])->firstOrFail();

        return new WebhookResponse(
            success: $data['payment']['status'] === 'SUCCEEDED',
            operationId: $invoice->id,
            operationType: PaymentOperationType::from($invoice->operation_type),
            message: $data['payment']['status'],
            providerResponse: ['alif_status' => $data['payment']['status']]
        );
    }

//    public function handleWebhook(array $data, string $signature = null, string $rawBody = null): WebhookResponse
//    {
//        if ($signature && $rawBody) {
//            $secretKey = config('alifpay.secret_key');
//            $expectedSignature = base64_encode(hash_hmac('sha256', $rawBody, $secretKey, true));
//
//            if (!hash_equals($expectedSignature, $signature)) {
//                throw new \Exception('Invalid webhook signature');
//            }
//        }
//
//        $invoice = Invoice::where('payment_provider_id', $data['id'])->firstOrFail();
//
//        return new WebhookResponse(
//            success: $data['payment']['status'] === 'SUCCEEDED',
//            operationId: $invoice->id,
//            operationType: PaymentOperationType::from($invoice->operation_type),
//            message: $data['payment']['status'],
//            providerResponse: ['alif_status' => $data['payment']['status']]
//        );
//    }
}
