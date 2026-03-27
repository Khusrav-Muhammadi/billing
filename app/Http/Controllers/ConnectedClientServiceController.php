<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\PartnerProcent;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectedClientServiceController extends Controller
{
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

    public function index()
    {
        $currencies = Currency::all()->keyBy('symbol_code');

        $tariffs = Tariff::where('is_tariff', true)
            ->with(['prices.currency'])
            ->get()
            ;

        $services = Tariff::where('is_tariff', false)
            ->with(['prices.currency'])
            ->get()
            ;

        $clients = Client::select('id', 'name', 'email', 'phone', 'currency_id')
            ->with('currency')
            ->orderBy('name')
            ->get();

        $partners = User::role();

        // Базовый конфиг — цены без client_id (для всех)
        $config = $this->buildConfig($currencies, $tariffs, $services, null);

        // Персональные цены по клиентам — { client_id: { tariff_key: { currency: price } } }
        $clientPrices = $this->buildClientPrices($tariffs, $services);

        return view('kp.index', [
            'config'       => $config,
            'clientPrices' => $clientPrices,
            'clients'      => $clients->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'email'    => $c->email ?? '',
                'phone'    => $c->phone ?? '',
                'currency' => $c->currency?->symbol_code,
            ]),
        ]);
    }

    /**
     * API endpoint for KP generator (iframe).
     * Returns unified config + clients + partners pulled from DB.
     */
    public function config(): JsonResponse
    {
        $currencies = Currency::all()->keyBy('symbol_code');

        $tariffs = Tariff::where('is_tariff', true)
            ->with(['prices.currency'])
            ->get()
            ;

        $services = Tariff::where('is_tariff', false)
            ->with(['prices.currency'])
            ->get()
            ;

        $clients = Client::select('id', 'name', 'email', 'phone', 'currency_id')
            ->with('currency')
            ->orderBy('name')
            ->get();

        // Partners live in users table (role column) in this project.
        $partners = User::query()
            ->whereRaw('LOWER(role) = ?', ['partner'])
            ->select('id', 'name', 'email', 'phone')
            ->orderBy('name')
            ->get();

        $partnerPercentsById = $this->getPartnerTariffPercents($partners->pluck('id')->all());

        $config = $this->buildConfig($currencies, $tariffs, $services, null);
        $clientPrices = $this->buildClientPrices($tariffs, $services);

        return response()->json([
            'config'        => $config,
            'client_prices' => $clientPrices,
            'clients'       => $clients->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'email'    => $c->email ?? '',
                'phone'    => $c->phone ?? '',
                'currency_id' => $c->currency_id,
                'currency' => $c->currency?->symbol_code,
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

        $clients = Client::select('id', 'name', 'email', 'phone', 'currency_id')
            ->with('currency')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'clients' => $clients->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'email'       => $c->email ?? '',
                'phone'       => $c->phone ?? '',
                'currency_id' => $c->currency_id,
                'currency'    => $c->currency?->symbol_code,
            ]),
        ]);
    }

    public function partners(Request $request): JsonResponse
    {
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

        $partnerPercentsById = $this->getPartnerTariffPercents($partners->pluck('id')->all());

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

    private function getPartnerTariffPercents(array $partnerIds): array
    {
        $partnerIds = array_values(array_filter(array_map('intval', $partnerIds)));
        if (!$partnerIds) return [];

        $today = strtotime(date('Y-m-d'));
        $best = []; // partnerId => ['ts' => int, 'id' => int, 'value' => int]

        $rows = PartnerProcent::query()
            ->whereIn('partner_id', $partnerIds)
            ->get();

        foreach ($rows as $row) {
            $pid = (string) $row->partner_id;
            $ts = $this->parseDateToTs($row->date) ?? 0;
            if ($ts > $today) continue;

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

    private function buildConfig($currencies, $tariffs, $services, $clientId = null): array
    {
        $today = strtotime(date('Y-m-d'));

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
            // Берём только общие цены (client_id = null)
            $prices = [];
            $bestByCurrency = [];
            foreach ($tariff->prices->whereNull('client_id') as $price) {
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

            $key = 'tariff-' . $tariff->id;

            $tariffsForJs[$key] = [
                'id'              => $tariff->id,
                'name'            => $tariff->name,
                'users'           => $tariff->user_count ?? 0,
                'prices'          => $prices,
                'prices12Months'  => array_map(fn($p) => round($p * 0.85, 2), $prices),
                'extraUserPrice'  => array_map(fn($p) => round($p * 0.10, 2), $prices),
                'includedServices' => [],
                'features'        => [],
            ];
        }

        // Услуги аналогично
        $servicesForJs = [];
        foreach ($services as $service) {
            $prices = [];
            $bestByCurrency = [];
            foreach ($service->prices->whereNull('client_id') as $price) {
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
                'hasChannels' => false,
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

// Собираем персональные цены для каждого клиента
    private function buildClientPrices($tariffs, $services): array
    {
        $result = [];
        $today = strtotime(date('Y-m-d'));

        foreach ($tariffs as $tariff) {
            $key = 'tariff-' . $tariff->id;

            foreach ($tariff->prices->whereNotNull('client_id') as $price) {
                $clientId = $price->client_id;
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
        }

        foreach ($services as $service) {
            $key = 'service-' . $service->id;

            foreach ($service->prices->whereNotNull('client_id') as $price) {
                $clientId = $price->client_id;
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
            foreach (['tariffs', 'services'] as $groupKey) {
                if (empty($result[$clientId][$groupKey])) continue;
                foreach ($result[$clientId][$groupKey] as $itemKey => $prices) {
                    unset($result[$clientId][$groupKey][$itemKey]['__meta']);
                }
            }
        }

        return $result;
    }
}
