<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\PartnerProcent;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->with(['prices.currency'])
            ->get()
            ;

        $services = Tariff::where('is_tariff', false)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with(['prices.currency'])
            ->get()
            ;

        $extraUserServicesByTariffId = Tariff::query()
            ->where('is_extra_user', true)
            ->with(['prices.currency'])
            ->get()
            ->groupBy('parent_tariff_id');

        $organizations = Organization::query()
            ->select('id', 'name', 'phone', 'client_id')
            ->with(['client.currency'])
            ->orderBy('name')
            ->get();

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
            ->with(['prices.currency'])
            ->get()
            ;

        $services = Tariff::where('is_tariff', false)
            ->where(function ($q) {
                $q->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with(['prices.currency'])
            ->get()
            ;

        $extraUserServicesByTariffId = Tariff::query()
            ->where('is_extra_user', true)
            ->with(['prices.currency'])
            ->get()
            ->groupBy('parent_tariff_id');

        $organizations = Organization::query()
            ->select('id', 'name', 'phone', 'client_id')
            ->with(['client.currency'])
            ->orderBy('name')
            ->get();

        // Partners live in users table (role column) in this project.
        $partners = User::query()
            ->whereRaw('LOWER(role) = ?', ['partner'])
            ->select('id', 'name', 'email', 'phone')
            ->orderBy('name')
            ->get();

        $partnerPercentsById = $this->getPartnerTariffPercents($partners->pluck('id')->all(), $asOfTs);

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
                'country_id'  => $o->client?->country_id,
                'currency_id' => $o->client?->currency_id,
                'currency'    => $o->client?->currency?->symbol_code,
            ]),
            'partners'      => $partners->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'email' => $p->email ?? '',
                'phone' => $p->phone ?? '',
                'procent_from_tariff' => (int) ($partnerPercentsById[(string) $p->id] ?? 0),
            ]),
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $organizations = Organization::query()
            ->select('id', 'name', 'phone', 'client_id')
            ->with(['client.currency'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            // Keep key name "clients" for backward-compatibility, but items are organizations.
            'clients' => $organizations->map(fn($o) => [
                'id'          => $o->id,
                'name'        => $o->name,
                'email'       => '',
                'phone'       => $o->phone ?? '',
                'country_id'  => $o->client?->country_id,
                'currency_id' => $o->client?->currency_id,
                'currency'    => $o->client?->currency?->symbol_code,
            ]),
        ]);
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
            ->select('id', 'name', 'email', 'phone')
            ->orderBy('name')
            ->limit(50)
            ->get();

        $partnerPercentsById = $this->getPartnerTariffPercents($partners->pluck('id')->all(), $asOfTs);

        return response()->json([
            'partners' => $partners->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'email' => $p->email ?? '',
                'phone' => $p->phone ?? '',
                'procent_from_tariff' => (int) ($partnerPercentsById[(string) $p->id] ?? 0),
            ]),
        ]);
    }

    private function getPartnerTariffPercents(array $partnerIds, int $asOfTs): array
    {
        $partnerIds = array_values(array_filter(array_map('intval', $partnerIds)));
        if (!$partnerIds) return [];

        $best = []; // partnerId => ['ts' => int, 'id' => int, 'value' => int]

        $rows = PartnerProcent::query()
            ->whereIn('partner_id', $partnerIds)
            ->get();

        foreach ($rows as $row) {
            $pid = (string) $row->partner_id;
            $ts = $this->parseDateToTs($row->date) ?? 0;
            if ($ts > $asOfTs) continue;

            $val = (int) ($row->procent_from_tariff ?? 0);
            if ($val < 0) $val = 0;
            if ($val > 100) $val = 100;

            $prev = $best[$pid] ?? null;
            if (
                !$prev
                || $ts > $prev['ts']
                || ($ts === $prev['ts'] && (int) $row->id > $prev['id'])
            ) {
                $best[$pid] = ['ts' => $ts, 'id' => (int) $row->id, 'value' => $val];
            }
        }

        $out = [];
        foreach ($best as $pid => $row) {
            $out[$pid] = $row['value'];
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

            // Temporary fallback (keeps old behavior until prices are filled in DB)
            if (empty($extraUserPrices)) {
                foreach ($prices as $symbol => $sum) {
                    $extraUserPrices[$symbol] = round(((float) $sum) * 0.10, 2);
                }
            }

            $key = 'tariff-' . $tariff->id;

            $tariffsForJs[$key] = [
                'id'              => $tariff->id,
                'name'            => $tariff->name,
                'users'           => $tariff->user_count ?? 0,
                'prices'          => $prices,
                'extraUserPrices' => $extraUserPrices,
                'prices12Months'  => array_map(fn($p) => round($p * 0.85, 2), $prices),
                'extraUserPrice'  => array_map(fn($p) => round($p * 0.10, 2), $prices),
                'includedServices' => [],
                'features'        => [],
            ];
        }

        // Услуги аналогично
        $servicesForJs = [];
        foreach ($services as $service) {
            $serviceEnd = $this->parseDateToTs($service->end_date ? (string) $service->end_date : null);
            if ($serviceEnd !== null && $serviceEnd < $today) continue;

            $prices = [];
            $bestByCurrency = [];
            foreach ($service->prices->whereNull('organization_id')->where('kind', 'base') as $price) {
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
                $prices[$symbol] = (float) $row['sum'];
            }

            if (empty($prices) && $service->price !== null) {
                foreach ($currenciesForJs as $symbolCode => $_currency) {
                    $prices[$symbolCode] = (float) $service->price;
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
            ];
        }

        return [
            'currencies'     => $currenciesForJs,
            'currenciesById' => $currenciesByIdForJs,
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
