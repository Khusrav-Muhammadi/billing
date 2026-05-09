<?php

namespace App\Services\Dashboard;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    private const REQUEST_TYPE_LABELS = [
        'connection' => 'Подключение',
        'connection_extra_services' => 'Доп. услуги',
        'renewal' => 'Продление с изменениями',
        'renewal_no_changes' => 'Продление без изменений',
        'unknown' => 'Не указан',
    ];

    private const MONTH_LABELS = [
        'Январь',
        'Февраль',
        'Март',
        'Апрель',
        'Май',
        'Июнь',
        'Июль',
        'Август',
        'Сентябрь',
        'Октябрь',
        'Ноябрь',
        'Декабрь',
    ];

    public function forYear(?int $year = null): array
    {
        $year = $this->normalizeYear($year);
        $currentMonth = (int) now()->month;
        $currencyContext = $this->buildCurrencyContext($year);

        $paymentGrossByMonth = $this->monthlySumUsd('client_payment_registries', 'date', 'gross_amount', 'tariff_currency_id', $year, $currencyContext);
        $paymentNetByMonth = $this->monthlySumUsd('client_payment_registries', 'date', 'net_amount', 'tariff_currency_id', $year, $currencyContext);
        $partnerExpenseByMonth = $this->monthlySumUsd('partner_expenses', 'date', 'partner_amount', 'currency_id', $year, $currencyContext);
        $discountExpenseByMonth = $this->monthlySumUsd('discount_expenses', 'date', 'discount_amount', 'currency_id', $year, $currencyContext);
        $implementationByMonth = $this->monthlySumUsd('implementation_currency_registries', 'date', 'total_amount', 'offer_currency_id', $year, $currencyContext);
        $connectedMonthlyByMonth = $this->monthlySumUsd('connected_client_services', 'date', 'service_total_amount', 'offer_currency_id', $year, $currencyContext);

        [$activeClientsByMonth, $inactiveClientsByMonth] = $this->connectionStatusesByMonth($year);
        [$operationTypeLabels, $operationTypeData, $operationTypeAmounts] = $this->operationTypes($year, $currencyContext);
        [$partnerUsers, $agentUsers] = $this->partnerStatusCounts();

        return [
            'dashboardYear' => $year,
            'monthLabels' => self::MONTH_LABELS,
            'currencyCode' => 'USD',
            'currencyRates' => $currencyContext['rates'],
            'missingCurrencyRates' => array_values(array_unique($currencyContext['missing'])),
            'cards' => [
                'active_clients' => $this->activeOrganizationsCount(),
                'month_income' => $paymentNetByMonth[$currentMonth - 1] ?? 0,
                'partners' => $this->partnersQuery()->count(),
                'partner_income' => $this->sumForYearUsd('client_payment_registries', 'date', 'net_amount', 'tariff_currency_id', $year, $currencyContext, true),
                'gross_income' => array_sum($paymentGrossByMonth),
                'net_income' => array_sum($paymentNetByMonth),
                'partner_expenses' => array_sum($partnerExpenseByMonth),
                'discount_expenses' => array_sum($discountExpenseByMonth),
                'implementation_income' => array_sum($implementationByMonth),
                'connected_monthly' => array_sum($connectedMonthlyByMonth),
            ],
            'operationTypeLabels' => $operationTypeLabels,
            'operationTypeData' => $operationTypeData,
            'operationTypeAmounts' => $operationTypeAmounts,
            'activeClientsByMonth' => $activeClientsByMonth,
            'inactiveClientsByMonth' => $inactiveClientsByMonth,
            'tariffRevenueChartData' => $this->tariffRevenueSeries($year, $currencyContext),
            'financialSeries' => [
                ['name' => 'Поступления', 'data' => $this->roundSeries($paymentGrossByMonth)],
                ['name' => 'Чистый доход', 'data' => $this->roundSeries($paymentNetByMonth)],
                ['name' => 'Партнерские расходы', 'data' => $this->roundSeries($partnerExpenseByMonth)],
                ['name' => 'Скидки', 'data' => $this->roundSeries($discountExpenseByMonth)],
                ['name' => 'Внедрение', 'data' => $this->roundSeries($implementationByMonth)],
                ['name' => 'Подключенные услуги / мес', 'data' => $this->roundSeries($connectedMonthlyByMonth)],
            ],
            'activePartners' => $partnerUsers,
            'inactivePartners' => $agentUsers,
        ];
    }

    private function normalizeYear(?int $year): int
    {
        $year = $year ?: (int) now()->year;

        if ($year < 2020 || $year > ((int) now()->year + 1)) {
            return (int) now()->year;
        }

        return $year;
    }

    private function monthlySumUsd(
        string $table,
        string $dateColumn,
        string $amountColumn,
        string $currencyColumn,
        int $year,
        array &$currencyContext
    ): array
    {
        $values = array_fill(0, 12, 0.0);

        $rows = DB::table($table)
            ->selectRaw("MONTH({$dateColumn}) as month_number, {$currencyColumn} as currency_id, {$amountColumn} as amount")
            ->whereNotNull($dateColumn)
            ->whereYear($dateColumn, $year)
            ->get();

        foreach ($rows as $row) {
            $monthIndex = ((int) $row->month_number) - 1;
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $values[$monthIndex] += $this->toUsd((float) $row->amount, $row->currency_id ? (int) $row->currency_id : null, $currencyContext);
            }
        }

        return $this->roundSeries($values);
    }

    private function sumForYearUsd(
        string $table,
        string $dateColumn,
        string $amountColumn,
        string $currencyColumn,
        int $year,
        array &$currencyContext,
        bool $onlyPartners = false
    ): float {
        $query = DB::table($table)
            ->select([$amountColumn, $currencyColumn])
            ->whereNotNull($dateColumn)
            ->whereYear($dateColumn, $year);

        if ($onlyPartners) {
            $query->whereNotNull('partner_id');
        }

        $total = 0.0;
        foreach ($query->get() as $row) {
            $total += $this->toUsd((float) $row->{$amountColumn}, $row->{$currencyColumn} ? (int) $row->{$currencyColumn} : null, $currencyContext);
        }

        return round($total, 2);
    }

    private function operationTypes(int $year, array &$currencyContext): array
    {
        $rows = DB::table('client_payment_registries')
            ->selectRaw("COALESCE(NULLIF(request_type, ''), 'unknown') as request_type, tariff_currency_id, net_amount")
            ->whereNotNull('date')
            ->whereYear('date', $year)
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $type = (string) $row->request_type;
            if (!isset($grouped[$type])) {
                $grouped[$type] = ['count' => 0, 'amount' => 0.0];
            }

            $grouped[$type]['count']++;
            $grouped[$type]['amount'] += $this->toUsd((float) $row->net_amount, $row->tariff_currency_id ? (int) $row->tariff_currency_id : null, $currencyContext);
        }

        uasort($grouped, static function (array $left, array $right): int {
            return $right['count'] <=> $left['count'];
        });

        $labels = [];
        $counts = [];
        $amounts = [];

        foreach ($grouped as $type => $data) {
            $labels[] = self::REQUEST_TYPE_LABELS[$type] ?? $type;
            $counts[] = (int) $data['count'];
            $amounts[] = round((float) $data['amount'], 2);
        }

        return [$labels, $counts, $amounts];
    }

    private function connectionStatusesByMonth(int $year): array
    {
        $active = array_fill(0, 12, 0);
        $inactive = array_fill(0, 12, 0);

        $rows = DB::table('organization_connection_statuses')
            ->selectRaw("MONTH(status_date) as month_number, status, COUNT(DISTINCT organization_id) as total")
            ->whereNotNull('status_date')
            ->whereYear('status_date', $year)
            ->groupByRaw('MONTH(status_date), status')
            ->get();

        foreach ($rows as $row) {
            $monthIndex = ((int) $row->month_number) - 1;
            if ($monthIndex < 0 || $monthIndex >= 12) {
                continue;
            }

            if ((string) $row->status === 'connected') {
                $active[$monthIndex] = (int) $row->total;
            } elseif ((string) $row->status === 'disconnected') {
                $inactive[$monthIndex] = (int) $row->total;
            }
        }

        return [$active, $inactive];
    }

    private function activeOrganizationsCount(): int
    {
        $latestIds = DB::table('organization_connection_statuses')
            ->selectRaw('MAX(id) as id')
            ->groupBy('organization_id');

        return (int) DB::table('organization_connection_statuses as statuses')
            ->joinSub($latestIds, 'latest_statuses', function ($join): void {
                $join->on('statuses.id', '=', 'latest_statuses.id');
            })
            ->where('statuses.status', 'connected')
            ->count();
    }

    private function tariffRevenueSeries(int $year, array &$currencyContext): array
    {
        $series = [];

        $rows = DB::table('connected_client_services as services')
            ->leftJoin('tariffs', 'tariffs.id', '=', 'services.tariff_id')
            ->selectRaw("COALESCE(tariffs.name, CONCAT('Услуга #', services.tariff_id)) as tariff_name, MONTH(services.date) as month_number, services.service_total_amount, services.offer_currency_id")
            ->whereNotNull('services.date')
            ->whereYear('services.date', $year)
            ->orderBy('tariff_name')
            ->get();

        foreach ($rows as $row) {
            $name = (string) $row->tariff_name;
            if (!isset($series[$name])) {
                $series[$name] = [
                    'name' => $name,
                    'data' => array_fill(0, 12, 0.0),
                ];
            }

            $monthIndex = ((int) $row->month_number) - 1;
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $series[$name]['data'][$monthIndex] += $this->toUsd(
                    (float) $row->service_total_amount,
                    $row->offer_currency_id ? (int) $row->offer_currency_id : null,
                    $currencyContext
                );
            }
        }

        foreach ($series as &$item) {
            $item['data'] = $this->roundSeries($item['data']);
        }

        return array_values($series);
    }

    private function buildCurrencyContext(int $year): array
    {
        $currencies = Currency::query()
            ->get(['id', 'symbol_code'])
            ->mapWithKeys(function (Currency $currency): array {
                return [(int) $currency->id => strtoupper((string) $currency->symbol_code)];
            })
            ->all();

        $usdCurrencyId = array_search('USD', $currencies, true);
        $rates = ['USD' => 1.0];
        $missing = [];

        if ($usdCurrencyId === false) {
            return [
                'codes' => $currencies,
                'rates' => $rates,
                'missing' => ['USD'],
            ];
        }

        $pairRates = $this->buildCurrencyPairRates(array_keys($currencies), sprintf('%d-12-31', $year));
        $graph = [];
        foreach ($pairRates as $pair => $rate) {
            [$baseId, $quoteId] = array_map('intval', explode(':', (string) $pair, 2));
            $rate = (float) $rate;
            if ($baseId <= 0 || $quoteId <= 0 || $baseId === $quoteId || $rate <= 0) {
                continue;
            }

            $graph[$baseId][$quoteId] = $rate;
            $graph[$quoteId][$baseId] ??= 1 / $rate;
        }

        foreach ($currencies as $currencyId => $code) {
            if ($code === 'USD') {
                continue;
            }

            $rate = $this->findCurrencyRateViaGraph($graph, (int) $usdCurrencyId, (int) $currencyId);
            if ($rate !== null && $rate > 0) {
                $rates[$code] = round($rate, 6);
            }
        }

        $missingCurrencyIds = [];
        foreach ($currencies as $currencyId => $code) {
            if ($code !== 'USD' && !isset($rates[$code])) {
                $missingCurrencyIds[] = (int) $currencyId;
            }
        }

        if (!empty($missingCurrencyIds)) {
            $fallbackRows = ExchangeRate::query()
                ->whereIn('currency_id', $missingCurrencyIds)
                ->orderByDesc('id')
                ->get(['currency_id', 'kurs']);

            foreach ($fallbackRows as $row) {
                $currencyId = (int) $row->currency_id;
                $code = $currencies[$currencyId] ?? null;
                $rate = (float) $row->kurs;
                if (!$code || isset($rates[$code]) || $rate <= 0) {
                    continue;
                }

                $rates[$code] = round($rate, 6);
            }
        }

        foreach ($currencies as $code) {
            if ($code !== 'USD' && !isset($rates[$code])) {
                $missing[] = $code;
            }
        }

        return [
            'codes' => $currencies,
            'rates' => $rates,
            'missing' => $missing,
        ];
    }

    private function buildCurrencyPairRates(array $currencyIds, string $asOfDate): array
    {
        $ids = array_values(array_filter(array_map('intval', $currencyIds), static fn (int $id): bool => $id > 0));
        if (empty($ids)) {
            return [];
        }

        $rows = CurrencyRate::query()
            ->whereIn('base_currency_id', $ids)
            ->whereIn('quote_currency_id', $ids)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get(['base_currency_id', 'quote_currency_id', 'rate', 'rate_date']);

        $dated = [];
        $latest = [];
        foreach ($rows as $row) {
            $baseId = (int) $row->base_currency_id;
            $quoteId = (int) $row->quote_currency_id;
            $rate = (float) $row->rate;
            if ($baseId <= 0 || $quoteId <= 0 || $baseId === $quoteId || $rate <= 0) {
                continue;
            }

            $pair = $baseId . ':' . $quoteId;
            $latest[$pair] ??= $rate;

            $rateDate = $row->rate_date instanceof \DateTimeInterface
                ? $row->rate_date->format('Y-m-d')
                : (string) $row->rate_date;

            if (!isset($dated[$pair]) && ($rateDate === '' || $rateDate <= $asOfDate)) {
                $dated[$pair] = $rate;
            }
        }

        return array_replace($latest, $dated);
    }

    private function findCurrencyRateViaGraph(array $graph, int $fromCurrencyId, int $toCurrencyId): ?float
    {
        if ($fromCurrencyId <= 0 || $toCurrencyId <= 0) {
            return null;
        }

        if ($fromCurrencyId === $toCurrencyId) {
            return 1.0;
        }

        $queue = [[$fromCurrencyId, 1.0]];
        $visited = [$fromCurrencyId => true];

        while (!empty($queue)) {
            [$currentId, $currentRate] = array_shift($queue);
            foreach (($graph[$currentId] ?? []) as $nextId => $edgeRate) {
                if (isset($visited[$nextId])) {
                    continue;
                }

                $nextRate = $currentRate * (float) $edgeRate;
                if ((int) $nextId === $toCurrencyId) {
                    return $nextRate;
                }

                $visited[$nextId] = true;
                $queue[] = [(int) $nextId, $nextRate];
            }
        }

        return null;
    }

    private function toUsd(float $amount, ?int $currencyId, array &$currencyContext): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $code = $currencyId ? ($currencyContext['codes'][$currencyId] ?? null) : 'USD';
        if (!$code || $code === 'USD') {
            return round($amount, 4);
        }

        $rate = (float) ($currencyContext['rates'][$code] ?? 0);
        if ($rate <= 0) {
            $currencyContext['missing'][] = $code;
            return round($amount, 4);
        }

        return round($amount / $rate, 4);
    }

    private function partnerStatusCounts(): array
    {
        $partnerCount = $this->partnersQuery()
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', 'partner');
            })
            ->count();
        $agentCount = $this->partnersQuery()
            ->where('status', 'agent')
            ->count();

        return [(int) $partnerCount, (int) $agentCount];
    }

    private function partnersQuery()
    {
        return User::query()->where('role', 'partner');
    }

    private function roundSeries(array $values): array
    {
        return array_map(static fn ($value): float => round((float) $value, 2), $values);
    }
}
