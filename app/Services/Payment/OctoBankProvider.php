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
            'total_amount' => $this->calculateTotalSum($dto),
            'provider' => PaymentProviderType::OCTOBANK,
            'additional_data' => json_encode([]),
            'operation_type' => $dto->operationType,
            'tariff_id' => $dto->metadata['operation_data']['tariff_id']
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
            $licensePrice = $this->applyDiscount($originalLicensePrice, 'license', $dto->metadata);
            $licenseSaleId = $dto->metadata['discounts']['license']['sale_id'] ?? null;

            if ($licensePrice > 0) {
                $items[] = $this->makeItem(
                    name: "Лицензия для тарифа {$dto->metadata['tariff_name']}",
                    price: $licensePrice,
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
        // Исправлено: используем newTariff->tariff_price как в AlifPayProvider
        $tariffPrice = $this->applyDiscount($dto->metadata['newTariff']->tariff_price, 'tariff', $dto->metadata);

        $items[] = $this->makeItem(
            name: "Изменение тарифа ({$dto->metadata['currentTariff']->name} → {$dto->metadata['newTariff']->tariff->name})",
            price: abs(($dto->metadata['organization_balance'] - ($licenseDifference + ($tariffPrice * $dto->metadata['months'])))),
            months: 1, // Исправлено: как в AlifPayProvider
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
            'price' => (float) number_format(round($price, 2), 2, '.', ''), // Просто передаем цену как есть
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
        $basket = [];
        $basketTotal = 0;

        foreach ($items as $item) {
            $itemPrice = round($item['price'], 2);
            $itemCount = $item['count'];

            $basketItem = [
                'position_desc' => $item['position_desc'],
                'count' => $itemCount,
                'price' => number_format($itemPrice, 2, '.', ''),
                'spic' => $item['spic']
            ];

            $basket[] = $basketItem;
            $basketTotal += $itemPrice * $itemCount;
        }

        $basketTotal = round($basketTotal, 2);

        $totalSumString = number_format($basketTotal, 2, '.', '');

        $shopTransactionId = $this->generateShopTransactionId($invoice->id);

        $payload = [
            'octo_shop_id' => (int) config('payments.octobank.shop_id'),
            'octo_secret' => config('payments.octobank.shop_secret'),
            'shop_transaction_id' => $shopTransactionId,
            'auto_capture' => true,
            'test' => false,
            'init_time' => now()->format('Y-m-d H:i:s'),
            'user_data' => [
                'user_id' => $dto->metadata['operation_data']['organization_id'],
                'phone' => ltrim($dto->metadata['phone'], '+'),
                'email' => $dto->metadata['email']
            ],
            'total_sum' => $totalSumString,
            'currency' => 'USD',
            'description' => $this->getPaymentDescription($dto->operationType, $dto->metadata),
            'basket' => $basket,
            'return_url' => $this->generateReturnUrl($dto),
            'notify_url' => 'https://billing-back.shamcrm.com/api/payment/webhook/OCTOBANK',
            'language' => config('payments.octobank.language', 'ru'),
            'ttl' => config('payments.octobank.ttl', 1440)
        ];

        Log::info('OctoBank final payload debug', [
            'total_sum' => $payload['total_sum'],
            'total_sum_type' => gettype($payload['total_sum']),
            'basket_verification' => array_sum(array_map(function($item) {
                return $item['price'] * $item['count'];
            }, $basket))
        ]);

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
                'message' => $responseData['apiMessageForDevelopers'] ?? 'Unknown error',
                'sent_payload' => $payload
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
        Log::error(json_encode($metadata));
        return match ($operationType) {
            PaymentOperationType::DEMO_TO_LIVE => "Активация лицензии и тарифа {$metadata['tariff_name']}",
            PaymentOperationType::TARIFF_RENEWAL => "Продление тарифа {$metadata['tariff_name']}",
            PaymentOperationType::TARIFF_CHANGE => "Смена тарифа на {$metadata['newTariff']->tariff->name}",
            PaymentOperationType::ADDON_PURCHASE => "Покупка пакета {$metadata['addon_name']}",
            default => 'Оплата услуг ShamCRM'
        };
    }

    private function generateReturnUrl(CreateInvoiceDTO $dto): string
    {
        return "https://fingroupcrm.shamcrm.com/payment";
    }

    private function calculateTotalSum(CreateInvoiceDTO $dto): float
    {
        return match ($dto->operationType) {
            PaymentOperationType::DEMO_TO_LIVE => $this->calculateDemoToLiveTotal($dto),
            PaymentOperationType::TARIFF_RENEWAL => $this->calculateTariffRenewalTotal($dto),
            PaymentOperationType::TARIFF_CHANGE => $this->calculateTariffChangeTotal($dto),
            PaymentOperationType::ADDON_PURCHASE => $this->calculateAddonTotal($dto),
            PaymentOperationType::ADD_ORGANIZATION => $this->calculateDemoToLiveTotal($dto), // Добавлено как в AlifPayProvider
        };
    }

    private function calculateDemoToLiveTotal(CreateInvoiceDTO $dto): float
    {
        $total = 0;

        if (($dto->metadata['license_price'] ?? 0) > 0) {
            $licensePrice = $this->applyDiscount($dto->metadata['license_price'], 'license', $dto->metadata);
            $total += $licensePrice;
        }

        $tariffPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);
        $total += $tariffPrice * $dto->metadata['operation_data']['months'];

        return $total; // Убрано round() как в AlifPayProvider
    }

    private function calculateTariffRenewalTotal(CreateInvoiceDTO $dto): float
    {
        $monthlyPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);
        return $monthlyPrice * $dto->metadata['months']; // Убрано round() как в AlifPayProvider
    }

    private function calculateAddonTotal(CreateInvoiceDTO $dto): float
    {
        return $this->applyDiscount($dto->metadata['addon_price'], 'addon', $dto->metadata); // Убрано round() как в AlifPayProvider
    }

    private function calculateTariffChangeTotal(CreateInvoiceDTO $dto): float
    {
        // Исправлено: используем логику как в AlifPayProvider
        $total = 0;

        if (($dto->metadata['license_difference'] ?? 0) > 0) {
            $licensePrice = $this->applyDiscount($dto->metadata['license_difference'], 'license', $dto->metadata);
            $total += $licensePrice;
        }

        $tariffPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);
        $total += $tariffPrice * $dto->metadata['operation_data']['months'];

        return $total; // Убрано round() и изменена логика как в AlifPayProvider
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
