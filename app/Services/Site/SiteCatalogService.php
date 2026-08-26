<?php

namespace App\Services\Site;

use App\Models\Currency;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use App\Services\Ai\AiTariffPlanCatalogService;
use Illuminate\Support\Collection;

class SiteCatalogService
{
    public function __construct(
        private readonly SiteOrganizationContextService $organizationContext,
        private readonly AiTariffPlanCatalogService $aiCatalog,
    ) {
    }

    public function build(int $organizationId, ?string $date = null): array
    {
        $context = $this->organizationContext->resolve($organizationId);
        $asOfTs = $this->parseDateToTs($date) ?? strtotime(date('Y-m-d'));
        $asOfDate = date('Y-m-d', $asOfTs);
        $currency = $context['currency'];
        $organization = $context['organization'];
        $country = $context['country'];

        return [
            'organization' => [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
                'country' => [
                    'id' => (int) $country->id,
                    'name' => (string) $country->name,
                ],
                'currency' => $currency,
            ],
            'date' => $asOfDate,
            'currency' => $currency,
            'payment_methods' => $context['payment_methods'],
            'tariffs' => $this->buildTariffs($currency, $asOfTs),
            'services' => $this->buildServices($currency, $asOfTs),
            'ai_tariff_plans' => $this->buildAiPlans($currency, $asOfDate),
        ];
    }

    private function buildTariffs(string $currency, int $asOfTs): array
    {
        $tariffs = Tariff::query()
            ->where('is_public', true)
            ->where('is_tariff', true)
            ->whereNull('partner_id')
            ->where(function ($query) {
                $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with([
                'prices.currency:id,symbol_code',
                'includedServices:id,name,is_tariff,is_extra_user,can_increase',
            ])
            ->orderBy('id')
            ->get();

        $extraByParent = Tariff::query()
            ->where('is_extra_user', true)
            ->with(['prices.currency:id,symbol_code'])
            ->get()
            ->groupBy('parent_tariff_id');

        $fallbackPrices = $this->tariffCurrencyFallback($tariffs->pluck('id')->all(), $currency);
        $items = [];

        foreach ($tariffs as $tariff) {
            $endTs = $this->parseDateToTs($tariff->end_date);
            if ($endTs !== null && $endTs < $asOfTs) {
                continue;
            }

            $price = $this->pickActivePrice($tariff->prices, $currency, $asOfTs, ['', 'base']);
            if ($price <= 0) {
                $price = (float) ($fallbackPrices[(int) $tariff->id] ?? 0);
            }
            if ($price <= 0) {
                continue;
            }

            $extraUserPrice = 0.0;
            $extraServices = $extraByParent->get((int) $tariff->id);
            if ($extraServices instanceof Collection) {
                foreach ($extraServices as $extraService) {
                    $candidate = $this->pickActivePrice($extraService->prices, $currency, $asOfTs, ['', 'base']);
                    if ($candidate > 0) {
                        $extraUserPrice = $candidate;
                        break;
                    }
                }
            }
            if ($extraUserPrice <= 0) {
                $extraUserPrice = $this->pickActivePrice($tariff->prices, $currency, $asOfTs, ['extra_user']);
            }

            $included = [];
            foreach ($tariff->includedServices as $service) {
                $included[] = [
                    'id' => (int) $service->id,
                    'name' => (string) $service->name,
                    'quantity' => max(1, (int) ($service->pivot?->quantity ?? 1)),
                    'is_paid' => (bool) ($service->pivot?->is_paid ?? false),
                    'can_increase' => (bool) $service->can_increase,
                ];
            }

            $items[] = [
                'id' => (int) $tariff->id,
                'name' => (string) $tariff->name,
                'category' => $this->nullableString($tariff->category),
                'users' => (int) ($tariff->user_count ?? 0),
                'currency' => $currency,
                'price' => $price,
                'price_12_months' => $this->money($price * 0.85),
                'extra_user_price' => $extraUserPrice > 0 ? $extraUserPrice : null,
                'included_services' => $included,
            ];
        }

        return $items;
    }

    private function buildServices(string $currency, int $asOfTs): array
    {
        $services = Tariff::query()
            ->where('is_public', true)
            ->where('is_tariff', false)
            ->whereNull('partner_id')
            ->where(function ($query) {
                $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with(['prices.currency:id,symbol_code'])
            ->orderBy('id')
            ->get();

        $fallbackPrices = $this->tariffCurrencyFallback($services->pluck('id')->all(), $currency);
        $items = [];

        foreach ($services as $service) {
            $endTs = $this->parseDateToTs($service->end_date);
            if ($endTs !== null && $endTs < $asOfTs) {
                continue;
            }

            $price = $this->pickActivePrice($service->prices, $currency, $asOfTs, ['', 'base']);
            if ($price <= 0) {
                $price = (float) ($fallbackPrices[(int) $service->id] ?? 0);
            }
            if ($price <= 0) {
                continue;
            }

            $items[] = [
                'id' => (int) $service->id,
                'name' => (string) $service->name,
                'category' => $this->nullableString($service->category),
                'currency' => $currency,
                'price' => $price,
                'has_channels' => (bool) $service->can_increase,
                'is_one_time' => (bool) ($service->is_one_time ?? false),
                'one_time_label' => (string) ($service->one_time_label ?? ''),
            ];
        }

        return $items;
    }

    private function buildAiPlans(string $currency, string $asOfDate): array
    {
        $currency = strtoupper($currency);
        $grouped = [
            'chat' => [],
            'call_analyse' => [],
        ];

        foreach ($this->aiCatalog->forCommercialOffer($asOfDate) as $plan) {
            $price = (float) ($plan['prices_by_currency'][$currency] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $category = (string) ($plan['category'] ?? 'chat');
            if (!isset($grouped[$category])) {
                $category = 'chat';
            }

            $grouped[$category][] = [
                'id' => (int) $plan['id'],
                'name' => (string) $plan['name'],
                'category' => $category,
                'model_name' => $plan['model_name'] ?? null,
                'currency' => $currency,
                'price' => $price,
                'periods' => $plan['periods'],
            ];
        }

        $result = [];
        foreach (['chat', 'call_analyse'] as $category) {
            $result[] = [
                'category' => $category,
                'label' => \App\Models\Ai\AiTariffPlan::categoryLabels()[$category] ?? $category,
                'plans' => $grouped[$category],
            ];
        }

        return $result;
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

    private function tariffCurrencyFallback(array $tariffIds, string $currency): array
    {
        if ($tariffIds === []) {
            return [];
        }

        $currencyId = (int) Currency::query()
            ->whereRaw('UPPER(symbol_code) = ?', [strtoupper($currency)])
            ->value('id');

        if ($currencyId <= 0) {
            return [];
        }

        $map = [];
        $rows = TariffCurrency::query()
            ->whereIn('tariff_id', $tariffIds)
            ->where('currency_id', $currencyId)
            ->get(['tariff_id', 'tariff_price']);

        foreach ($rows as $row) {
            $price = $this->money($row->tariff_price);
            if ($price > 0) {
                $map[(int) $row->tariff_id] = $price;
            }
        }

        return $map;
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

    private function nullableString(mixed $value): ?string
    {
        $raw = trim((string) $value);

        return $raw !== '' ? $raw : null;
    }
}
