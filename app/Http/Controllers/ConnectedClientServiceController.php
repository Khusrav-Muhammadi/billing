<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Models\Client;
use App\Models\ConnectedClientServices;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\PartnerProcent;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectedClientServiceController extends Controller
{
    private function getAsOfTs(Request $request): int
    {
        $ts = $this->parseDateToTs((string) $request->query('date', ''));
        return $ts ?? strtotime(date('Y-m-d'));
    }

    private function parseDateToTs(?string $value): ?int
    {
        $v = trim((string) $value);
        if ($v === '') return null;

        $ts = strtotime($v);
        if ($ts !== false) return $ts;

        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $v);
            if ($dt instanceof \DateTime) {
                return $dt->getTimestamp();
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        $asOfTs = $this->getAsOfTs($request);
        $currencies = Currency::all()->keyBy('symbol_code');

        $tariffs = Tariff::where('is_tariff', true)
            ->with(['prices.currency', 'includedServices'])
            ->get()
            ;

        $services = Tariff::where('is_tariff', false)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with([
                'prices.currency',
                'excludedOrganizations' => function ($query) {
                    $query->select('organizations.id');
                },
            ])
            ->get()
            ;

        $extraUserServicesByTariffId = Tariff::query()
            ->where('is_extra_user', true)
            ->with(['prices.currency'])
            ->get()
            ->groupBy('parent_tariff_id');

        $organizations = Organization::query()
            ->select('id', 'name', 'phone', 'client_id', 'order_number')
            ->with(['client.country.currency'])
            ->orderBy('name')
            ->get();
        $operationStartDates = $this->getOrganizationOperationStartDates($organizations->pluck('id')->all());

        $partners = User::role();

        // Базовый конфиг — цены без organization_id (для всех)
        $config = $this->buildConfig($currencies, $tariffs, $services, $extraUserServicesByTariffId, $asOfTs, null);

        // Персональные цены по организациям — { organization_id: { tariff_key: { currency: price } } }
        $clientPrices = $this->buildClientPrices($tariffs, $services, $extraUserServicesByTariffId, $asOfTs);

        return view('kp.index', [
            'config'       => $config,
            'clientPrices' => $clientPrices,
            'clients'      => $clients->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'email'    => $c->email ?? '',
                'phone'    => $c->phone ?? '',
                'country_id' => $c->country_id,
                'currency' => $c->currency?->symbol_code,
            ]),
        ]);
    }

    /**
     * API endpoint for KP generator (iframe).
     * Returns unified config + clients + partners pulled from DB.
     */
    public function config(Request $request): JsonResponse
    {
        $asOfTs = $this->getAsOfTs($request);
        $currencies = Currency::all()->keyBy('symbol_code');

        $tariffs = Tariff::where('is_tariff', true)
            ->with(['prices.currency', 'includedServices'])
            ->get()
            ;

        $services = Tariff::where('is_tariff', false)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with([
                'prices.currency',
                'excludedOrganizations' => function ($query) {
                    $query->select('organizations.id');
                },
            ])
            ->get()
            ;

        $extraUserServicesByTariffId = Tariff::query()
            ->where('is_extra_user', true)
            ->with(['prices.currency'])
            ->get()
            ->groupBy('parent_tariff_id');

        $organizations = Organization::query()
            ->select('id', 'name', 'phone', 'client_id', 'order_number')
            ->with(['client.country.currency'])
            ->orderBy('name')
            ->get();
        $operationStartDates = $this->getOrganizationOperationStartDates($organizations->pluck('id')->all());

        // Partners live in users table (role column) in this project.
        $partners = User::query()
            ->whereRaw('LOWER(role) = ?', ['partner'])
            ->select('id', 'name', 'email', 'phone', 'payment_methods')
            ->orderBy('name')
            ->get();

        $partnerPercentsById = $this->getPartnerPercents($partners->pluck('id')->all(), $asOfTs);

        $config = $this->buildConfig($currencies, $tariffs, $services, $extraUserServicesByTariffId, $asOfTs, null);
        $clientPrices = $this->buildClientPrices($tariffs, $services, $extraUserServicesByTariffId, $asOfTs);

        return response()->json([
            'config'        => $config,
            'client_prices' => $clientPrices,
            // Keep key name "clients" for backward-compatibility, but items are organizations.
            'clients'       => $organizations->map(fn($o) => [
                'id'          => $o->id,
                'name'        => $o->name,
                'email'       => '',
                'phone'       => $o->phone ?? '',
                'order_number'=> $o->order_number,
                'operation_start_date' => $operationStartDates[(int) $o->id] ?? null,
                'country_id'  => $o->client?->country_id,
                'currency_id' => $o->client?->country?->currency_id,
                'currency'    => $o->client?->country?->currency?->symbol_code,
            ]),
            'partners'      => $partners->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'email' => $p->email ?? '',
                'phone' => $p->phone ?? '',
                'procent_from_tariff' => (int) ($partnerPercentsById[(string) $p->id]['tariff'] ?? 0),
                'procent_from_pack' => (int) ($partnerPercentsById[(string) $p->id]['pack'] ?? 0),
                'payment_methods' => $this->normalizePartnerPaymentMethods($p->payment_methods ?? null),
            ]),
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $organizations = Organization::query()
            ->select('id', 'name', 'phone', 'client_id', 'order_number')
            ->with(['client.country.currency'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('order_number', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();
        $operationStartDates = $this->getOrganizationOperationStartDates($organizations->pluck('id')->all());

        return response()->json([
            'clients' => $organizations->map(fn($o) => [
                'id'          => $o->id,
                'name'        => $o->name,
                'email'       => '',
                'phone'       => $o->phone ?? '',
                'order_number'=> $o->order_number,
                'operation_start_date' => $operationStartDates[(int) $o->id] ?? null,
                'country_id'  => $o->client?->country_id,
                'currency_id' => $o->client?->country?->currency_id,
                'currency'    => $o->client?->country?->currency?->symbol_code,
            ]),
        ]);
    }

    private function getOrganizationOperationStartDates(array $organizationIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $organizationIds)));
        if (empty($ids)) {
            return [];
        }

        $rows = ConnectedClientServices::query()
            ->whereIn('client_id', $ids)
            ->where('status', true)
            ->select('client_id', DB::raw('MIN(`date`) as operation_start_at'))
            ->groupBy('client_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $organizationId = (int) ($row->client_id ?? 0);
            if ($organizationId <= 0) {
                continue;
            }

            $value = trim((string) ($row->operation_start_at ?? ''));
            if ($value === '') {
                continue;
            }

            $ts = strtotime($value);
            if ($ts === false) {
                continue;
            }

            $result[$organizationId] = date('Y-m-d', $ts);
        }

        return $result;
    }

    public function partners(Request $request): JsonResponse
    {
        $asOfTs = $this->getAsOfTs($request);
        $search = trim((string) $request->query('search', ''));

        $partners = User::query()
            ->whereRaw('LOWER(role) = ?', ['partner'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->select('id', 'name', 'email', 'phone', 'payment_methods')
            ->orderBy('name')
            ->limit(50)
            ->get();

        $partnerPercentsById = $this->getPartnerPercents($partners->pluck('id')->all(), $asOfTs);

        return response()->json([
            'partners' => $partners->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'email' => $p->email ?? '',
                'phone' => $p->phone ?? '',
                'procent_from_tariff' => (int) ($partnerPercentsById[(string) $p->id]['tariff'] ?? 0),
                'procent_from_pack' => (int) ($partnerPercentsById[(string) $p->id]['pack'] ?? 0),
                'payment_methods' => $this->normalizePartnerPaymentMethods($p->payment_methods ?? null),
            ]),
        ]);
    }

    private function normalizePartnerPaymentMethods($methods): array
    {
        $allowed = ['card', 'invoice', 'cash'];
        $defaults = ['card', 'invoice'];

        if (is_string($methods)) {
            $decoded = json_decode($methods, true);
            if (is_array($decoded)) {
                $methods = $decoded;
            }
        }

        if (!is_array($methods)) {
            return $defaults;
        }

        $set = [];
        foreach ($methods as $method) {
            $code = strtolower(trim((string) $method));
            if (in_array($code, $allowed, true)) {
                $set[$code] = true;
            }
        }

        if (empty($set)) {
            return $defaults;
        }

        $ordered = [];
        foreach ($allowed as $method) {
            if (isset($set[$method])) {
                $ordered[] = $method;
            }
        }

        return $ordered;
    }

    /**
     * @return array<string,array{tariff:int,pack:int}>
     */
    private function getPartnerPercents(array $partnerIds, int $asOfTs): array
    {
        $partnerIds = array_values(array_filter(array_map('intval', $partnerIds)));
        if (!$partnerIds) return [];

        $best = [];

        $rows = PartnerProcent::query()
            ->whereIn('partner_id', $partnerIds)
            ->get();

        foreach ($rows as $row) {
            $pid = (string) $row->partner_id;
            $ts = $this->parseDateToTs($row->date) ?? 0;
            if ($ts > $asOfTs) continue;

            $tariffVal = (int) ($row->procent_from_tariff ?? 0);
            if ($tariffVal < 0) $tariffVal = 0;
            if ($tariffVal > 100) $tariffVal = 100;

            $packVal = (int) ($row->procent_from_pack ?? 0);
            if ($packVal < 0) $packVal = 0;
            if ($packVal > 100) $packVal = 100;

            $prev = $best[$pid] ?? null;
            if (
                !$prev
                || $ts > $prev['ts']
                || ($ts === $prev['ts'] && (int) $row->id > $prev['id'])
            ) {
                $best[$pid] = [
                    'ts' => $ts,
                    'id' => (int) $row->id,
                    'tariff' => $tariffVal,
                    'pack' => $packVal,
                ];
            }
        }

        $out = [];
        foreach ($best as $pid => $row) {
            $out[$pid] = [
                'tariff' => (int) ($row['tariff'] ?? 0),
                'pack' => (int) ($row['pack'] ?? 0),
            ];
        }
        return $out;
    }

    private function buildConfig($currencies, $tariffs, $services, $extraUserServicesByTariffId, int $asOfTs, $clientId = null): array
    {
        $today = $asOfTs;

        $currenciesForJs = [];
        $currenciesByIdForJs = [];
        foreach ($currencies as $symbolCode => $currency) {
            $currenciesForJs[$symbolCode] = [
                'symbol' => $currency->symbol_code,
                'name'   => $currency->name,
            ];
            $currenciesByIdForJs[(string) $currency->id] = $currency->symbol_code;
        }

        $tariffsForJs = [];
        foreach ($tariffs as $tariff) {
            $tariffEnd = $this->parseDateToTs($tariff->end_date ? (string) $tariff->end_date : null);
            if ($tariffEnd !== null && $tariffEnd < $today) continue;

            // Берём только общие цены (organization_id = null)
            $prices = [];
            $bestByCurrency = [];
            foreach ($tariff->prices->whereNull('organization_id')->where('kind', 'base') as $price) {
                $symbol = $price->currency?->symbol_code;
                if (!$symbol) continue;

                $start = $this->parseDateToTs($price->start_date);
                $end = $this->parseDateToTs($price->date);

                // Apply by date range: start <= today <= end (null means open-ended)
                if ($start !== null && $start > $today) continue;
                if ($end !== null && $end < $today) continue;

                $startScore = $start ?? 0;
                $endScore = $end ?? PHP_INT_MAX;
                $prev = $bestByCurrency[$symbol] ?? null;
                if (
                    !$prev
                    || $startScore > $prev['start']
                    || ($startScore === $prev['start'] && $endScore >= $prev['end'])
                ) {
                    $bestByCurrency[$symbol] = [
                        'start' => $startScore,
                        'end' => $endScore,
                        'sum' => (float) $price->sum,
                    ];
                }
            }
            foreach ($bestByCurrency as $symbol => $row) {
                $prices[$symbol] = (float) $row['sum'];
            }

            // Fallback: if prices table is empty, use tariffs.price (same value for all currencies)
            if (empty($prices) && $tariff->price !== null) {
                foreach ($currenciesForJs as $symbolCode => $_currency) {
                    $prices[$symbolCode] = (float) $tariff->price;
                }
            }

            if (empty($prices)) continue;

            // Extra user prices (per additional user, per month)
            // Preferred source: "extra user" service linked to this tariff (tariffs.is_extra_user=1, parent_tariff_id=tariff.id)
            $extraUserPrices = [];
            $bestExtraByCurrency = [];

            $extraServices = $extraUserServicesByTariffId && isset($extraUserServicesByTariffId[$tariff->id])
                ? $extraUserServicesByTariffId[$tariff->id]
                : null;
            $extraUserTariffId = $extraServices && $extraServices->isNotEmpty()
                ? (int) $extraServices->first()->id
                : null;

            if ($extraServices) {
                foreach ($extraServices as $extraService) {
                    foreach ($extraService->prices->whereNull('organization_id')->where('kind', 'base') as $price) {
                        $symbol = $price->currency?->symbol_code;
                        if (!$symbol) continue;

                        $start = $this->parseDateToTs($price->start_date);
                        $end = $this->parseDateToTs($price->date);

                        if ($start !== null && $start > $today) continue;
                        if ($end !== null && $end < $today) continue;

                        $startScore = $start ?? 0;
                        $endScore = $end ?? PHP_INT_MAX;
                        $prev = $bestExtraByCurrency[$symbol] ?? null;
                        if (
                            !$prev
                            || $startScore > $prev['start']
                            || ($startScore === $prev['start'] && $endScore >= $prev['end'])
                        ) {
                            $bestExtraByCurrency[$symbol] = [
                                'start' => $startScore,
                                'end' => $endScore,
                                'sum' => (float) $price->sum,
                            ];
                        }
                    }
                }
            }

            // Backward-compatible source: prices.kind=extra_user on the tariff itself
            if (empty($bestExtraByCurrency)) {
                foreach ($tariff->prices->whereNull('organization_id')->where('kind', 'extra_user') as $price) {
                    $symbol = $price->currency?->symbol_code;
                    if (!$symbol) continue;

                    $start = $this->parseDateToTs($price->start_date);
                    $end = $this->parseDateToTs($price->date);

                    if ($start !== null && $start > $today) continue;
                    if ($end !== null && $end < $today) continue;

                    $startScore = $start ?? 0;
                    $endScore = $end ?? PHP_INT_MAX;
                    $prev = $bestExtraByCurrency[$symbol] ?? null;
                    if (
                        !$prev
                        || $startScore > $prev['start']
                        || ($startScore === $prev['start'] && $endScore >= $prev['end'])
                    ) {
                        $bestExtraByCurrency[$symbol] = [
                            'start' => $startScore,
                            'end' => $endScore,
                            'sum' => (float) $price->sum,
                        ];
                    }
                }
            }

            foreach ($bestExtraByCurrency as $symbol => $row) {
                $extraUserPrices[$symbol] = (float) $row['sum'];
            }

            // If there is no extra user price in DB for a currency, it must be treated as 0.

            $key = 'tariff-' . $tariff->id;

            $includedServicesKeys = [];
            $includedQty = [];
            foreach (($tariff->includedServices ?? []) as $includedService) {
                $serviceKey = 'service-' . $includedService->id;
                $includedServicesKeys[] = $serviceKey;
                $includedQty[$serviceKey] = (int) ($includedService->pivot?->quantity ?? 1);
            }

            $tariffsForJs[$key] = [
                'id'              => $tariff->id,
                'name'            => $tariff->name,
                'users'           => $tariff->user_count ?? 0,
                'extraUserTariffId' => $extraUserTariffId,
                'prices'          => $prices,
                'extraUserPrices' => $extraUserPrices,
                'prices12Months'  => array_map(fn($p) => round($p * 0.85, 4), $prices),
                'extraUserPrice'  => $extraUserPrices,
                'includedServices' => $includedServicesKeys,
                'includedServiceQuantities' => $includedQty,
                'features'        => [],
            ];
        }

        // Услуги аналогично
        $servicesForJs = [];
        foreach ($services as $service) {
            $serviceEnd = $this->parseDateToTs($service->end_date ? (string) $service->end_date : null);
            $isActiveByEndDate = $serviceEnd === null || $serviceEnd >= $today;

            $excludedOrganizationIds = collect($service->excludedOrganizations ?? [])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
            $hasExcludedOrganizations = !empty($excludedOrganizationIds);

            if (!$isActiveByEndDate && !$hasExcludedOrganizations) continue;

            $basePrices = $service->prices->whereNull('organization_id')->where('kind', 'base');

            $activePrices = [];
            $bestByCurrency = [];
            foreach ($basePrices as $price) {
                $symbol = $price->currency?->symbol_code;
                if (!$symbol) continue;

                $start = $this->parseDateToTs($price->start_date);
                $end = $this->parseDateToTs($price->date);

                if ($start !== null && $start > $today) continue;
                if ($end !== null && $end < $today) continue;

                $startScore = $start ?? 0;
                $endScore = $end ?? PHP_INT_MAX;
                $prev = $bestByCurrency[$symbol] ?? null;
                if (
                    !$prev
                    || $startScore > $prev['start']
                    || ($startScore === $prev['start'] && $endScore >= $prev['end'])
                ) {
                    $bestByCurrency[$symbol] = [
                        'start' => $startScore,
                        'end' => $endScore,
                        'sum' => (float) $price->sum,
                    ];
                }
            }
            foreach ($bestByCurrency as $symbol => $row) {
                $activePrices[$symbol] = (float) $row['sum'];
            }

            $prices = $activePrices;

            if (empty($prices) && $service->price !== null) {
                foreach ($currenciesForJs as $symbolCode => $_currency) {
                    $prices[$symbolCode] = (float) $service->price;
                }
            }

            $isAvailableOnDate = $isActiveByEndDate && !empty($prices);

            // For organizations in exclusions we keep service visible even if date is outdated.
            // In that case use the latest price not newer than selected date.
            if (empty($prices) && $hasExcludedOrganizations) {
                $fallbackBestByCurrency = [];
                foreach ($basePrices as $price) {
                    $symbol = $price->currency?->symbol_code;
                    if (!$symbol) continue;

                    $start = $this->parseDateToTs($price->start_date);
                    $end = $this->parseDateToTs($price->date);

                    if ($start !== null && $start > $today) continue;

                    $startScore = $start ?? 0;
                    $endScore = $end ?? PHP_INT_MAX;
                    $prev = $fallbackBestByCurrency[$symbol] ?? null;
                    if (
                        !$prev
                        || $startScore > $prev['start']
                        || ($startScore === $prev['start'] && $endScore >= $prev['end'])
                    ) {
                        $fallbackBestByCurrency[$symbol] = [
                            'start' => $startScore,
                            'end' => $endScore,
                            'sum' => (float) $price->sum,
                        ];
                    }
                }

                foreach ($fallbackBestByCurrency as $symbol => $row) {
                    $prices[$symbol] = (float) $row['sum'];
                }
            }

            if (empty($prices)) continue;

            $key = 'service-' . $service->id;

            $servicesForJs[$key] = [
                'id'          => $service->id,
                'name'        => $service->name,
                'description' => '',
                'type'        => 'monthly',
                'prices'      => $prices,
                'hasChannels' => (bool) ($service->can_increase ?? false),
                'isAvailableOnDate' => $isAvailableOnDate,
                'excludedOrganizationIds' => $excludedOrganizationIds,
            ];
        }

        return [
            'currencies'     => $currenciesForJs,
            'currenciesById' => $currenciesByIdForJs,
            'usdRates'       => $this->buildUsdRates($currencies, $asOfTs),
            'paymentPeriods' => [
                ['months' => 6,  'discount' => 0,  'label' => '6 месяцев'],
                ['months' => 12, 'discount' => 15, 'label' => '12 месяцев (скидка 15%)'],
            ],
            'tariffs'  => $tariffsForJs,
            'services' => $servicesForJs,
            'company'  => [
                'name'    => 'SHAMCRM',
                'phone'   => '+998785557416',
                'email'   => 'info@shamcrm.com',
                'website' => 'shamcrm.com',
            ],
        ];
    }

    private function buildUsdRates($currencies, int $asOfTs): array
    {
        $usdCurrency = $currencies->get('USD');
        if (!$usdCurrency) {
            return [];
        }

        $rates = ['USD' => 1.0];
        $quotes = $currencies->filter(fn ($currency, $code) => $code !== 'USD');
        if ($quotes->isEmpty()) {
            return $rates;
        }

        $quoteIdToSymbol = [];
        foreach ($quotes as $symbolCode => $currency) {
            $quoteIdToSymbol[(int) $currency->id] = $symbolCode;
        }

        $quoteIds = array_keys($quoteIdToSymbol);
        $asOfDate = date('Y-m-d', $asOfTs);

        // Preferred: latest rate not newer than selected date.
        $datedRows = CurrencyRate::query()
            ->where('base_currency_id', $usdCurrency->id)
            ->whereIn('quote_currency_id', $quoteIds)
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('rate_date')
                    ->orWhere('rate_date', '<=', $asOfDate);
            })
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get(['quote_currency_id', 'rate']);

        $usedQuoteIds = [];
        foreach ($datedRows as $row) {
            $quoteId = (int) $row->quote_currency_id;
            if (isset($usedQuoteIds[$quoteId])) {
                continue;
            }

            $rate = (float) $row->rate;
            if ($rate <= 0) {
                continue;
            }

            $symbolCode = $quoteIdToSymbol[$quoteId] ?? null;
            if (!$symbolCode) {
                continue;
            }

            $rates[$symbolCode] = $rate;
            $usedQuoteIds[$quoteId] = true;
        }

        // Fallback: if no historical rate found, use the latest available.
        $missingQuoteIds = array_values(array_filter($quoteIds, fn ($id) => !isset($usedQuoteIds[$id])));
        if (!empty($missingQuoteIds)) {
            $latestRows = CurrencyRate::query()
                ->where('base_currency_id', $usdCurrency->id)
                ->whereIn('quote_currency_id', $missingQuoteIds)
                ->orderByDesc('rate_date')
                ->orderByDesc('id')
                ->get(['quote_currency_id', 'rate']);

            $latestUsed = [];
            foreach ($latestRows as $row) {
                $quoteId = (int) $row->quote_currency_id;
                if (isset($latestUsed[$quoteId])) {
                    continue;
                }

                $rate = (float) $row->rate;
                if ($rate <= 0) {
                    continue;
                }

                $symbolCode = $quoteIdToSymbol[$quoteId] ?? null;
                if (!$symbolCode) {
                    continue;
                }

                $rates[$symbolCode] = $rate;
                $latestUsed[$quoteId] = true;
            }
        }

        return $rates;
    }

// Собираем персональные цены для каждой организации
    private function buildClientPrices($tariffs, $services, $extraUserServicesByTariffId, int $asOfTs): array
    {
        $result = [];
        $today = $asOfTs;

        foreach ($tariffs as $tariff) {
            $key = 'tariff-' . $tariff->id;

            // Extra users: preferred source is linked "extra user" service
            $extraServices = $extraUserServicesByTariffId && isset($extraUserServicesByTariffId[$tariff->id])
                ? $extraUserServicesByTariffId[$tariff->id]
                : null;

            if ($extraServices) {
                foreach ($extraServices as $extraService) {
                    foreach ($extraService->prices->whereNotNull('organization_id')->where('kind', 'base') as $price) {
                        $clientId = $price->organization_id;
                        $symbol   = $price->currency?->symbol_code;
                        if (!$symbol) continue;

                        $start = $this->parseDateToTs($price->start_date);
                        $end = $this->parseDateToTs($price->date);

                        if ($start !== null && $start > $today) continue;
                        if ($end !== null && $end < $today) continue;

                        $startScore = $start ?? 0;
                        $endScore = $end ?? PHP_INT_MAX;

                        $prev = $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['score'] ?? null;
                        $prevEnd = $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['end'] ?? null;
                        if (
                            $prev === null
                            || $startScore > $prev
                            || ($startScore === $prev && $endScore >= ($prevEnd ?? 0))
                        ) {
                            $result[$clientId]['extra_users'][$key][$symbol] = (float) $price->sum;
                            $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['score'] = $startScore;
                            $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['end'] = $endScore;
                        }
                    }
                }
            }

            foreach ($tariff->prices->whereNotNull('organization_id')->where('kind', 'base') as $price) {
                $clientId = $price->organization_id;
                $symbol   = $price->currency?->symbol_code;
                if (!$symbol) continue;
                $start = $this->parseDateToTs($price->start_date);
                $end = $this->parseDateToTs($price->date);

                if ($start !== null && $start > $today) continue;
                if ($end !== null && $end < $today) continue;

                $startScore = $start ?? 0;
                $endScore = $end ?? PHP_INT_MAX;

                $prev = $result[$clientId]['tariffs'][$key]['__meta'][$symbol]['score'] ?? null;
                $prevEnd = $result[$clientId]['tariffs'][$key]['__meta'][$symbol]['end'] ?? null;
                if (
                    $prev === null
                    || $startScore > $prev
                    || ($startScore === $prev && $endScore >= ($prevEnd ?? 0))
                ) {
                    $result[$clientId]['tariffs'][$key][$symbol] = (float) $price->sum;
                    $result[$clientId]['tariffs'][$key]['__meta'][$symbol]['score'] = $startScore;
                    $result[$clientId]['tariffs'][$key]['__meta'][$symbol]['end'] = $endScore;
                }
            }

            // Backward-compatible: prices.kind=extra_user on the tariff itself (only if not set by extra service)
            foreach ($tariff->prices->whereNotNull('organization_id')->where('kind', 'extra_user') as $price) {
                $clientId = $price->organization_id;
                $symbol   = $price->currency?->symbol_code;
                if (!$symbol) continue;

                $start = $this->parseDateToTs($price->start_date);
                $end = $this->parseDateToTs($price->date);

                if ($start !== null && $start > $today) continue;
                if ($end !== null && $end < $today) continue;

                // If extra user price already exists for this client/currency from linked service, keep it.
                if (isset($result[$clientId]['extra_users'][$key][$symbol])) continue;

                $startScore = $start ?? 0;
                $endScore = $end ?? PHP_INT_MAX;

                $prev = $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['score'] ?? null;
                $prevEnd = $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['end'] ?? null;
                if (
                    $prev === null
                    || $startScore > $prev
                    || ($startScore === $prev && $endScore >= ($prevEnd ?? 0))
                ) {
                    $result[$clientId]['extra_users'][$key][$symbol] = (float) $price->sum;
                    $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['score'] = $startScore;
                    $result[$clientId]['extra_users'][$key]['__meta'][$symbol]['end'] = $endScore;
                }
            }
        }

        foreach ($services as $service) {
            $key = 'service-' . $service->id;

            foreach ($service->prices->whereNotNull('organization_id')->where('kind', 'base') as $price) {
                $clientId = $price->organization_id;
                $symbol   = $price->currency?->symbol_code;
                if (!$symbol) continue;
                $start = $this->parseDateToTs($price->start_date);
                $end = $this->parseDateToTs($price->date);

                if ($start !== null && $start > $today) continue;
                if ($end !== null && $end < $today) continue;

                $startScore = $start ?? 0;
                $endScore = $end ?? PHP_INT_MAX;

                $prev = $result[$clientId]['services'][$key]['__meta'][$symbol]['score'] ?? null;
                $prevEnd = $result[$clientId]['services'][$key]['__meta'][$symbol]['end'] ?? null;
                if (
                    $prev === null
                    || $startScore > $prev
                    || ($startScore === $prev && $endScore >= ($prevEnd ?? 0))
                ) {
                    $result[$clientId]['services'][$key][$symbol] = (float) $price->sum;
                    $result[$clientId]['services'][$key]['__meta'][$symbol]['score'] = $startScore;
                    $result[$clientId]['services'][$key]['__meta'][$symbol]['end'] = $endScore;
                }
            }
        }

        // Strip meta
        foreach ($result as $clientId => $groups) {
            foreach (['tariffs', 'services', 'extra_users'] as $groupKey) {
                if (empty($result[$clientId][$groupKey])) continue;
                foreach ($result[$clientId][$groupKey] as $itemKey => $prices) {
                    unset($result[$clientId][$groupKey][$itemKey]['__meta']);
                }
            }
        }

        return $result;
    }
}
