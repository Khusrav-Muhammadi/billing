<?php

namespace App\Services\Site;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use App\Services\Payment\OnlineCheckoutLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SitePaymentLinkService
{
    public function create(array $payload): array
    {
        $paymentType = $this->normalizePaymentType((string) ($payload['payment_type'] ?? ''));
        $currency = $paymentType === 'alif' ? 'UZS' : 'USD';
        $asOfTs = $this->parseDateToTs($payload['date'] ?? null) ?? strtotime(date('Y-m-d'));
        $periodMonths = max(1, (int) ($payload['period_months'] ?? 6));
        $extraUsers = max(0, (int) ($payload['extra_users'] ?? 0));
        $tariffId = (int) ($payload['tariff_id'] ?? 0);
        $requestedServices = $this->normalizeRequestedServices($payload['services'] ?? []);

        if ($tariffId <= 0 && $requestedServices === [] && $extraUsers <= 0) {
            throw ValidationException::withMessages([
                'tariff_id' => 'Выберите тариф или услуги для оплаты.',
            ]);
        }

        $items = $this->buildPayableItems(
            tariffId: $tariffId,
            requestedServices: $requestedServices,
            extraUsers: $extraUsers,
            periodMonths: $periodMonths,
            currency: $currency,
            asOfTs: $asOfTs
        );

        if ($items === []) {
            throw ValidationException::withMessages([
                'services' => 'Нет позиций для оплаты. Проверьте тариф, услуги и что они публичные.',
            ]);
        }

        $sum = round(array_reduce($items, static fn (float $carry, array $item): float => $carry + (float) $item['price'], 0.0), 4);
        if ($sum <= 0) {
            throw ValidationException::withMessages([
                'sum' => 'Итоговая сумма должна быть больше 0.',
            ]);
        }

        $payment = DB::transaction(function () use ($payload, $paymentType, $sum, $items): Payment {
            $payment = Payment::query()->create([
                'name' => trim((string) $payload['name']),
                'phone' => preg_replace('/\D+/', '', (string) $payload['phone']) ?: '',
                'email' => trim((string) $payload['email']),
                'sum' => $sum,
                'payment_type' => $paymentType,
            ]);

            foreach ($items as $item) {
                PaymentItem::query()->create([
                    'payment_id' => $payment->id,
                    'service_name' => $item['name'],
                    'price' => $item['price'],
                ]);
            }

            return $payment->fresh('paymentItems');
        });

        $paymentUrl = app(OnlineCheckoutLinkService::class)->createUrl($payment);

        return [
            'payment_id' => (int) $payment->id,
            'payment_type' => $paymentType === 'alif' ? 'alif' : 'visa',
            'currency' => $currency,
            'period_months' => $periodMonths,
            'sum' => $sum,
            'payment_url' => $paymentUrl,
            'redirect_url' => $paymentUrl,
            'items' => $items,
        ];
    }

    private function buildPayableItems(
        int $tariffId,
        array $requestedServices,
        int $extraUsers,
        int $periodMonths,
        string $currency,
        int $asOfTs
    ): array {
        $items = [];
        $tariff = null;
        $includedByServiceId = [];

        if ($tariffId > 0) {
            $tariff = $this->findPublicTariff($tariffId, true);
            $monthly = $this->resolvePrice($tariff, $currency, $asOfTs);
            $discountedMonthly = $this->applyPeriodDiscount($monthly, $periodMonths);
            $items[] = [
                'id' => (int) $tariff->id,
                'type' => 'tariff',
                'name' => sprintf('Тариф "%s" (%d мес)', $tariff->name, $periodMonths),
                'quantity' => 1,
                'unit_price' => $discountedMonthly,
                'price' => $this->money($discountedMonthly * $periodMonths),
            ];

            foreach ($tariff->includedServices as $included) {
                $includedByServiceId[(int) $included->id] = [
                    'quantity' => max(1, (int) ($included->pivot?->quantity ?? 1)),
                    'is_paid' => (bool) ($included->pivot?->is_paid ?? false),
                    'can_increase' => (bool) $included->can_increase,
                ];
            }
        }

        if ($extraUsers > 0) {
            if (!$tariff) {
                throw ValidationException::withMessages([
                    'extra_users' => 'Доп. пользователей можно указать только вместе с тарифом.',
                ]);
            }

            $extraMonthly = $this->resolveExtraUserPrice($tariff, $currency, $asOfTs);
            $items[] = [
                'id' => (int) ($tariff->id),
                'type' => 'extra_users',
                'name' => sprintf('Доп. пользователи (×%d)', $extraUsers),
                'quantity' => $extraUsers,
                'unit_price' => $extraMonthly,
                'price' => $this->money($extraMonthly * $extraUsers * $periodMonths),
            ];
        }

        foreach ($requestedServices as $requested) {
            $service = $this->findPublicTariff((int) $requested['id'], false);
            $quantity = max(1, (int) $requested['quantity']);
            $included = $includedByServiceId[(int) $service->id] ?? null;
            $chargeableQty = $quantity;

            if ($included && !$included['is_paid']) {
                if ($included['can_increase']) {
                    $chargeableQty = max(0, $quantity - (int) $included['quantity']);
                } else {
                    $chargeableQty = 0;
                }
            }

            if ($chargeableQty <= 0) {
                continue;
            }

            $unitPrice = $this->resolvePrice($service, $currency, $asOfTs);
            $isOneTime = (bool) ($service->is_one_time ?? false);
            $lineTotal = $isOneTime
                ? $unitPrice * $chargeableQty
                : $unitPrice * $chargeableQty * $periodMonths;

            $name = (string) $service->name;
            if ($chargeableQty > 1) {
                $name .= sprintf(' (×%d)', $chargeableQty);
            }

            $items[] = [
                'id' => (int) $service->id,
                'type' => 'service',
                'name' => $name,
                'quantity' => $chargeableQty,
                'unit_price' => $unitPrice,
                'price' => $this->money($lineTotal),
            ];
        }

        return $items;
    }

    private function findPublicTariff(int $id, bool $mustBeTariff): Tariff
    {
        $tariff = Tariff::query()
            ->with([
                'prices.currency:id,symbol_code',
                'includedServices:id,name,is_tariff,is_extra_user,can_increase',
            ])
            ->find($id);

        if (!$tariff) {
            throw ValidationException::withMessages([
                $mustBeTariff ? 'tariff_id' : 'services' => 'Тариф или услуга не найдены.',
            ]);
        }

        if (!(bool) ($tariff->is_public ?? false)) {
            throw ValidationException::withMessages([
                $mustBeTariff ? 'tariff_id' : 'services' => sprintf('«%s» недоступен для оплаты с сайта.', $tariff->name),
            ]);
        }

        if ((bool) $tariff->is_extra_user) {
            throw ValidationException::withMessages([
                'services' => 'Доп. пользователей передавайте в extra_users, а не в услугах.',
            ]);
        }

        if ($mustBeTariff && !(bool) $tariff->is_tariff) {
            throw ValidationException::withMessages([
                'tariff_id' => 'Указанный id не является тарифом.',
            ]);
        }

        if (!$mustBeTariff && (bool) $tariff->is_tariff) {
            throw ValidationException::withMessages([
                'services' => 'Тариф нужно передавать в tariff_id, а не в услугах.',
            ]);
        }

        return $tariff;
    }

    private function resolvePrice(Tariff $tariff, string $currency, int $asOfTs): float
    {
        $fromPrices = $this->pickActivePrice($tariff->prices, $currency, $asOfTs, ['', 'base']);
        if ($fromPrices > 0) {
            return $fromPrices;
        }

        $currencyId = $this->currencyIdByCode($currency);
        if ($currencyId > 0) {
            $fallback = TariffCurrency::query()
                ->where('tariff_id', $tariff->id)
                ->where('currency_id', $currencyId)
                ->value('tariff_price');
            $fallback = (float) $fallback;
            if ($fallback > 0) {
                return $this->money($fallback);
            }
        }

        throw ValidationException::withMessages([
            'currency' => sprintf('Нет цены в %s для «%s».', $currency, $tariff->name),
        ]);
    }

    private function resolveExtraUserPrice(Tariff $tariff, string $currency, int $asOfTs): float
    {
        $extraService = Tariff::query()
            ->where('is_extra_user', true)
            ->where('parent_tariff_id', $tariff->id)
            ->with(['prices.currency:id,symbol_code'])
            ->first();

        if ($extraService) {
            $price = $this->pickActivePrice($extraService->prices, $currency, $asOfTs, ['', 'base']);
            if ($price > 0) {
                return $price;
            }
        }

        $price = $this->pickActivePrice($tariff->prices, $currency, $asOfTs, ['extra_user']);
        if ($price > 0) {
            return $price;
        }

        throw ValidationException::withMessages([
            'extra_users' => sprintf('Нет цены доп. пользователя в %s для тарифа «%s».', $currency, $tariff->name),
        ]);
    }

    private function pickActivePrice(iterable $priceRows, string $currency, int $asOfTs, array $kinds): float
    {
        $best = null;

        foreach ($priceRows as $priceRow) {
            if (data_get($priceRow, 'organization_id') !== null) {
                continue;
            }

            $kind = mb_strtolower(trim((string) data_get($priceRow, 'kind', '')));
            if (!in_array($kind, $kinds, true)) {
                continue;
            }

            $code = strtoupper(trim((string) data_get($priceRow, 'currency.symbol_code', '')));
            if ($code !== $currency) {
                continue;
            }

            $startTs = $this->parseDateToTs(data_get($priceRow, 'start_date'));
            $endTs = $this->parseDateToTs(data_get($priceRow, 'date'));
            if ($startTs !== null && $startTs > $asOfTs) {
                continue;
            }
            if ($endTs !== null && $endTs < $asOfTs) {
                continue;
            }

            $sum = $this->money(data_get($priceRow, 'sum', 0));
            if ($sum <= 0) {
                continue;
            }

            $startScore = $startTs ?? 0;
            $endScore = $endTs ?? PHP_INT_MAX;
            if (
                $best === null
                || $startScore > $best['start']
                || ($startScore === $best['start'] && $endScore >= $best['end'])
            ) {
                $best = ['start' => $startScore, 'end' => $endScore, 'sum' => $sum];
            }
        }

        return $best['sum'] ?? 0.0;
    }

    private function applyPeriodDiscount(float $monthly, int $periodMonths): float
    {
        $percent = $periodMonths === 12 ? 15 : 0;

        return $this->money($monthly * (1 - $percent / 100));
    }

    private function normalizeRequestedServices(mixed $services): array
    {
        if (!is_array($services)) {
            return [];
        }

        $normalized = [];
        foreach ($services as $row) {
            if (is_numeric($row)) {
                $id = (int) $row;
                $quantity = 1;
            } elseif (is_array($row)) {
                $id = (int) ($row['id'] ?? $this->extractIdFromKey((string) ($row['key'] ?? $row['service_key'] ?? '')));
                $quantity = max(1, (int) ($row['quantity'] ?? $row['channels'] ?? 1));
            } elseif (is_string($row)) {
                $id = $this->extractIdFromKey($row);
                $quantity = 1;
            } else {
                continue;
            }

            if ($id <= 0) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    private function extractIdFromKey(string $key): int
    {
        if (preg_match('/(?:tariff|service)-(\d+)/', trim($key), $matches) === 1) {
            return (int) $matches[1];
        }

        return ctype_digit(trim($key)) ? (int) $key : 0;
    }

    private function normalizePaymentType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'alif' => 'alif',
            'visa', 'octo' => 'octo',
            default => throw ValidationException::withMessages([
                'payment_type' => 'Тип оплаты должен быть visa или alif.',
            ]),
        };
    }

    private function currencyIdByCode(string $code): int
    {
        return (int) Currency::query()
            ->whereRaw('UPPER(symbol_code) = ?', [strtoupper($code)])
            ->value('id');
    }

    private function parseDateToTs(mixed $value): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return $ts;
        }

        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if ($dt instanceof \DateTime) {
                return $dt->getTimestamp();
            }
        }

        return null;
    }

    private function money(mixed $value): float
    {
        return round((float) str_replace(',', '.', (string) $value), 4);
    }
}
