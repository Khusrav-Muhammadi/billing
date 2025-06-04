<?php

namespace App\Services\Payment;


use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Payment\Enums\PaymentProviderType;
use App\Services\Payment\Enums\PaymentStatus;
use App\Services\Payment\Enums\TransactionPurpose;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;


class InvoiceProvider implements PaymentProviderInterface
{
    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse
    {
        $invoice = $this->createInvoiceRecord($dto);

        $invoiceItems = $this->prepareInvoiceItems($invoice->id, $dto);

        foreach ($invoiceItems as $invoiceItem) {
            InvoiceItem::query()->create($invoiceItem);
        }

        $invoiceNumber = $this->generateInvoiceNumber($invoice->id);

        $invoice->payment_provider_id = $invoiceNumber;
        $invoice->additional_data = json_encode([
            'invoice_number' => $invoiceNumber,
            'created_date' => now()->format('d.m.Y'),
            'due_date' => now()->addDays(7)->format('d.m.Y')
        ]);

        $invoice->save();

        $downloadUrl = route('invoice.download', ['invoice' => $invoice->id, 'token' => $this->generateSecureToken($invoice->id)]);

        return new PaymentResponse(
            success: true,
            paymentUrl: $downloadUrl,
            providerInvoiceId: $invoiceNumber
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
            'provider' => PaymentProviderType::INVOICE,
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
                    quantity: 1,
                    sale_id: $licenseSaleId
                );
            }
        }

        $originalTariffPrice = $dto->metadata['tariff_price'];
        $tariffPrice = $this->applyDiscount($originalTariffPrice, 'tariff', $dto->metadata);
        $tariffSaleId = $dto->metadata['discounts']['tariff']['sale_id'] ?? null;

        $items[] = $this->makeItem(
            name: "Активация тарифа {$dto->metadata['tariff_name']} ({$dto->metadata['operation_data']['months']} мес.)",
            price: $tariffPrice,
            months: $dto->metadata['operation_data']['months'],
            invoiceId: $invoiceId,
            purpose: TransactionPurpose::TARIFF,
            quantity: $dto->metadata['operation_data']['months'],
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
                name: "Продление тарифа {$dto->metadata['tariff_name']} ({$months} мес.)",
                price: $monthlyPrice,
                months: $months,
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::TARIFF,
                quantity: $months,
                sale_id: $tariffSaleId
            )
        ];
    }

    private function tariffChangeItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $items = [];

        $tariffSaleId = $dto->metadata['discounts']['tariff']['sale_id'] ?? null;
        $licenseDifference = $this->applyDiscount($dto->metadata['license_difference'], 'license', $dto->metadata);
        $tariffPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);

        $items[] = $this->makeItem(
            name: "Изменение тарифа ({$dto->metadata['currentTariff']->name} → {$dto->metadata['newTariff']->tariff->name})",
            price: abs(($dto->metadata['organization_balance'] - ($licenseDifference + ($tariffPrice * $dto->metadata['months'])))),
            months: $dto->metadata['operation_data']['months'],
            invoiceId: $invoiceId,
            purpose: TransactionPurpose::CHANGE_TARIFF,
            quantity: 1,
            sale_id: $tariffSaleId
        );

        return $items;
    }

    private function addonItems(int $invoiceId, CreateInvoiceDTO $dto): array
    {
        $originalAddonPrice = $dto->metadata['addon_price'];
        $addonPrice = $this->applyDiscount($originalAddonPrice, 'addon', $dto->metadata);
        $addonSaleId = $dto->metadata['discounts']['addon']['sale_id'] ?? null;

        $quantity = $dto->metadata['addon_type'] === 'one-time' ? 1 : $dto->metadata['operation_data']['months'];

        return [
            $this->makeItem(
                name: $dto->metadata['addon_type'] === 'one-time'
                    ? "Пакет {$dto->metadata['addon_name']}"
                    : "Пакет {$dto->metadata['addon_name']} (ежемесячно, {$dto->metadata['operation_data']['months']} мес.)",
                price: $addonPrice,
                months: $dto->metadata['operation_data']['months'],
                invoiceId: $invoiceId,
                purpose: TransactionPurpose::ADDON_PACKAGE,
                quantity: $quantity,
                sale_id: $addonSaleId
            )
        ];
    }

    private function applyDiscount(float $originalPrice, string $discountType, array $metadata): float
    {
        if (!isset($metadata['discounts'][$discountType])) {
            return round($originalPrice, 2);
        }

        $discount = $metadata['discounts'][$discountType];
        $percent = floatval($discount['percent']);

        if ($discountType === 'tariff' && isset($discount['months_required'])) {
            $months = $metadata['months'] ?? $metadata['operation_data']['months'] ?? 1;
            if ($months < $discount['months_required']) {
                return round($originalPrice, 2);
            }
        }

        $discountAmount = ($originalPrice * $percent) / 100;
        return round(max(0, $originalPrice - $discountAmount), 2);
    }

    private function makeItem(string $name, float $price, int $months, int $invoiceId, TransactionPurpose $purpose, int $quantity, int $sale_id = null): array
    {
        $unitPrice = $purpose == TransactionPurpose::TARIFF ? $price : $price;
        $totalPrice = $unitPrice * $quantity;

        return [
            'name' => $name,
            'amount' => $quantity,
            'price' => round($totalPrice, 2),
            'unit_price' => round($unitPrice, 2),
            'invoice_id' => $invoiceId,
            'purpose' => $purpose,
            'sale_id' => $sale_id
        ];
    }

    private function generateInvoiceNumber(int $invoiceId): string
    {
        return str_pad($invoiceId, 6, '0', STR_PAD_LEFT);
    }

    private function generateSecureToken(int $invoiceId): string
    {
        return hash('sha256', $invoiceId . config('app.key') . now()->format('Y-m-d'));
    }

    private function calculateTotalSum(CreateInvoiceDTO $dto): float
    {
        return match ($dto->operationType) {
            PaymentOperationType::DEMO_TO_LIVE => $this->calculateDemoToLiveTotal($dto),
            PaymentOperationType::TARIFF_RENEWAL => $this->calculateTariffRenewalTotal($dto),
            PaymentOperationType::TARIFF_CHANGE => $this->calculateTariffChangeTotal($dto),
            PaymentOperationType::ADDON_PURCHASE => $this->calculateAddonTotal($dto),
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

        return round($total, 2);
    }

    private function calculateTariffRenewalTotal(CreateInvoiceDTO $dto): float
    {
        $monthlyPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);
        return round($monthlyPrice * $dto->metadata['months'], 2);
    }

    private function calculateAddonTotal(CreateInvoiceDTO $dto): float
    {
        return round($this->applyDiscount($dto->metadata['addon_price'], 'addon', $dto->metadata), 2);
    }

    private function calculateTariffChangeTotal(CreateInvoiceDTO $dto): float
    {
        $licenseDifference = $this->applyDiscount($dto->metadata['license_difference'], 'license', $dto->metadata);
        $tariffPrice = $this->applyDiscount($dto->metadata['tariff_price'], 'tariff', $dto->metadata);

        $total = abs(($dto->metadata['organization_balance'] - ($licenseDifference + ($tariffPrice * $dto->metadata['months']))));
        return round($total, 2);
    }

    public function generatePDF(Invoice $invoice): Response
    {
        $organization = $this->getOrganizationData($invoice->organization_id);

        $invoiceItems = InvoiceItem::where('invoice_id', $invoice->id)->get();

        $data = [
            'invoice' => $invoice,
            'organization' => $organization,
            'invoiceItems' => $invoiceItems,
            'invoiceData' => json_decode($invoice->additional_data, true),
            'companyData' => $this->getCompanyData(),
            'totalAmount' => $invoice->total_amount,
            'currency' => $this->getCurrencySymbol($invoice->currency_id)
        ];

        $pdf = Pdf::loadView('invoices.invoice', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        $fileName = "Счет_{$data['invoiceData']['invoice_number']}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    private function getOrganizationData(int $organizationId): array
    {
        $org = Organization::find($organizationId);

        return [
            'legal_name' => $org->legal_name ?? 'Не указано',
            'legal_address' => $org->legal_address ?? 'Не указано',
            'inn' => $org->inn ?? 'Не указано',
            'phone' => $org->phone ?? 'Не указано',
            'director' => $org->director ?? 'Не указано',
            'email' => $org->email ?? 'Не указано'
        ];
    }

    private function getCompanyData(): array
    {
        return [
            'name' => 'Общество с ограниченной ответственностью "SOFTTECH GROUP"',
            'address' => 'г. Ташкент, Яккасарайский район Богсарай, улица Мирабад 10',
            'phone' => '+998773756868',
            'inn' => '31168486',
            'oked' => '62010',
            'account' => '20208000807159380001',
            'bank' => 'КАПИТАЛБАНК "КАПИТАЛ 24" ЧАКАНА БИЗНЕС ФИЛИАЛИ',
            'mfo' => '01158',
            'director' => 'Ахмедов М.Р.'
        ];
    }

    private function getCurrencySymbol(int $currencyId): string
    {
        return match ($currencyId) {
            1 => '$',
            2 => 'сум',
            3 => '₽',
            default => 'сум'
        };
    }

    public function handleWebhook(array $data): WebhookResponse
    {
        throw new \BadMethodCallException('Webhook handling is not supported for invoice payments');
    }
}
