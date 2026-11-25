<?php

namespace App\Services\PartnerProgram;

use App\Models\ClientHealthSnapshot;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Отчёт по партнёрам для админки портала: рабочий стол, список партнёров, карточка, выгрузка руководителю.
 *
 * Продажа партнёра — оплаченное КП (commercial_offers.status = paid) с его partner_id; дата оплаты —
 * commercial_offers.status_date (при оплате туда пишется дата оплаты). Подключение — КП с request_type = connection,
 * продление — renewal и renewal_no_changes. Атрибуция по КП, а не по clients.partner_id: владелец клиента
 * меняется (ручная правка, «Взять клиента», автоочистка у демо), а КП фиксирует, кто продал.
 *
 * Светофор партнёра (только одобренные): по последнему подключению за всё время.
 *   green — было подключение за последний месяц;
 *   yellow — последнее 1–3 месяца назад;
 *   red — больше 3 месяцев назад или ни разу;
 *   new — ни разу не подключал, но одобрен меньше месяца назад (чтобы новички не падали в красные).
 */
class PartnerReportService
{
    public const LIGHT_GREEN = 'green';
    public const LIGHT_YELLOW = 'yellow';
    public const LIGHT_RED = 'red';
    public const LIGHT_NEW = 'new';
    public const LIGHTS = [self::LIGHT_GREEN, self::LIGHT_YELLOW, self::LIGHT_RED, self::LIGHT_NEW];

    private const RENEWAL_TYPES = ['renewal', 'renewal_no_changes'];

    public function __construct(private readonly PartnerProgramService $program)
    {
    }

    public function report(Carbon $from, Carbon $to): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $partners = User::query()
            ->where('role', 'partner')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'country_id', 'approval_status', 'approved_at', 'created_at', 'demo_client_id']);
        $ids = $partners->pluck('id')->all();

        $currencyCodes = Currency::query()->pluck('symbol_code', 'id')->map(fn ($c) => strtoupper((string) $c));

        // Последнее подключение за всё время — для светофора.
        $lastConnection = DB::table('commercial_offers')
            ->whereIn('partner_id', $ids)
            ->where('status', 'paid')
            ->where('request_type', 'connection')
            ->groupBy('partner_id')
            ->selectRaw('partner_id, MAX(status_date) AS d, COUNT(*) AS c')
            ->get()
            ->keyBy('partner_id');

        // Оплаченные КП за период: подключения, продления, оборот по валютам.
        $periodOffers = DB::table('commercial_offers')
            ->whereIn('partner_id', $ids)
            ->where('status', 'paid')
            ->whereBetween('status_date', [$fromDate, $toDate])
            ->groupBy('partner_id', 'request_type', 'currency')
            ->selectRaw('partner_id, request_type, UPPER(COALESCE(currency, ?)) AS cur, COUNT(*) AS c, SUM(grand_total) AS total', ['USD'])
            ->get()
            ->groupBy('partner_id');

        // Комиссия партнёров за период (partner_expenses).
        $commission = DB::table('partner_expenses')
            ->whereIn('partner_id', $ids)
            ->whereBetween('date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('partner_id', 'currency_id')
            ->selectRaw('partner_id, currency_id, SUM(partner_amount) AS total')
            ->get()
            ->groupBy('partner_id');

        // Клиенты с действующим подключением (как active_clients в списке партнёров).
        $activeClients = DB::table('organizations')
            ->join('clients', 'clients.id', '=', 'organizations.client_id')
            ->whereIn('clients.partner_id', $ids)
            ->where('clients.nfr', false)
            ->whereRaw("(SELECT ocs.status FROM organization_connection_statuses ocs
                WHERE ocs.organization_id = organizations.id
                ORDER BY ocs.status_date DESC, ocs.id DESC LIMIT 1) = 'connected'")
            ->groupBy('clients.partner_id')
            ->selectRaw('clients.partner_id AS pid, COUNT(DISTINCT organizations.client_id) AS c')
            ->pluck('c', 'pid');

        // Светофор клиентов партнёра на последний посчитанный день (только платные).
        $healthDate = ClientHealthSnapshot::query()->max('date');
        $health = collect();
        if ($healthDate) {
            $health = DB::table('client_health_snapshots')
                ->join('clients', 'clients.id', '=', 'client_health_snapshots.client_id')
                ->whereDate('client_health_snapshots.date', $healthDate)
                ->where('client_health_snapshots.client_type', 'paid')
                ->whereIn('clients.partner_id', $ids)
                ->groupBy('clients.partner_id', 'client_health_snapshots.color')
                ->selectRaw('clients.partner_id AS pid, client_health_snapshots.color AS color, COUNT(*) AS c')
                ->get()
                ->groupBy('pid');
        }

        $nfr = $this->nfrByPartner($partners);

        $rates = [];
        $toUzs = function (string $code) use (&$rates): ?float {
            if (!array_key_exists($code, $rates)) {
                $rates[$code] = $this->program->convert(1.0, $code, 'UZS');
            }

            return $rates[$code];
        };

        $now = now();
        $rows = [];
        foreach ($partners as $partner) {
            $pid = (int) $partner->id;
            $approval = $partner->approval_status ?? PartnerProgramService::STATUS_APPROVED;
            $last = $lastConnection[$pid] ?? null;
            $lastAt = $last?->d ? Carbon::parse($last->d) : null;

            $connections = 0;
            $renewals = 0;
            $turnover = [];
            foreach ($periodOffers[$pid] ?? [] as $row) {
                $type = (string) $row->request_type;
                if ($type === 'connection') {
                    $connections += (int) $row->c;
                } elseif (in_array($type, self::RENEWAL_TYPES, true)) {
                    $renewals += (int) $row->c;
                }
                $turnover[$row->cur] = round(($turnover[$row->cur] ?? 0) + (float) $row->total, 2);
            }

            $turnoverUzs = 0.0;
            foreach ($turnover as $code => $sum) {
                $rate = $toUzs($code);
                $turnoverUzs += $rate === null ? 0 : $sum * $rate;
            }

            $commissionByCurrency = [];
            foreach ($commission[$pid] ?? [] as $row) {
                $code = $currencyCodes[$row->currency_id] ?? 'USD';
                $commissionByCurrency[$code] = round(($commissionByCurrency[$code] ?? 0) + (float) $row->total, 2);
            }

            $healthCounts = array_fill_keys(ClientHealthSnapshot::COLOR_ORDER, 0);
            foreach ($health[$pid] ?? [] as $row) {
                $healthCounts[$row->color] = (int) $row->c;
            }

            $rows[] = [
                'id' => $pid,
                'name' => $partner->name,
                'email' => $partner->email,
                'phone' => $partner->phone,
                'country_id' => $partner->country_id,
                'approval_status' => $approval,
                'approved_at' => $partner->approved_at ? Carbon::parse($partner->approved_at)->toDateTimeString() : null,
                'created_at' => optional($partner->created_at)->toDateTimeString(),
                'light' => $approval === PartnerProgramService::STATUS_APPROVED ? $this->light($lastAt, $partner, $now) : null,
                'last_connection_at' => $lastAt?->toDateString(),
                'connections_total' => (int) ($last?->c ?? 0),
                'connections' => $connections,
                'renewals' => $renewals,
                'turnover_by_currency' => $turnover,
                'turnover_uzs' => round($turnoverUzs, 2),
                'commission_by_currency' => $commissionByCurrency,
                'active_clients' => (int) ($activeClients[$pid] ?? 0),
                'health' => $healthCounts,
                'nfr' => $nfr[$pid] ?? null,
            ];
        }

        return [
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'health_date' => $healthDate ? Carbon::parse($healthDate)->toDateString() : null,
            'missing_rates' => array_keys(array_filter($rates, fn ($r) => $r === null)),
            'totals' => $this->totals($rows),
            'partners' => $rows,
        ];
    }

    private function light(?Carbon $lastAt, User $partner, Carbon $now): string
    {
        if ($lastAt) {
            if ($lastAt->greaterThanOrEqualTo($now->copy()->subMonth()->startOfDay())) {
                return self::LIGHT_GREEN;
            }

            return $lastAt->greaterThanOrEqualTo($now->copy()->subMonths(3)->startOfDay()) ? self::LIGHT_YELLOW : self::LIGHT_RED;
        }

        $since = $partner->approved_at ?: $partner->created_at;

        return $since && Carbon::parse($since)->greaterThanOrEqualTo($now->copy()->subMonth())
            ? self::LIGHT_NEW
            : self::LIGHT_RED;
    }

    /**
     * NFR партнёра: его рабочая CRM (users.demo_client_id, иначе клиент с nfr = true и его partner_id).
     * Пользуется ли — по clients.last_activity, который присылает CRM.
     */
    private function nfrByPartner($partners): array
    {
        $byDemoId = $partners->filter(fn ($p) => $p->demo_client_id)->pluck('demo_client_id', 'id');

        $clients = DB::table('clients')
            ->where(function ($q) use ($byDemoId, $partners) {
                $q->whereIn('id', $byDemoId->values()->all())
                    ->orWhere(fn ($q2) => $q2->where('nfr', true)->whereIn('partner_id', $partners->pluck('id')->all()));
            })
            ->get(['id', 'partner_id', 'sub_domain', 'last_activity', 'is_active', 'nfr']);

        $hasAccess = DB::table('organizations')
            ->whereIn('client_id', $clients->pluck('id')->all())
            ->groupBy('client_id')
            ->selectRaw('client_id, MAX(has_access) AS a')
            ->pluck('a', 'client_id');

        $result = [];
        foreach ($partners as $partner) {
            $client = $partner->demo_client_id
                ? $clients->firstWhere('id', $partner->demo_client_id)
                : null;
            $client ??= $clients->first(fn ($c) => $c->nfr && (int) $c->partner_id === (int) $partner->id);
            if (!$client) {
                continue;
            }

            $result[(int) $partner->id] = [
                'client_id' => (int) $client->id,
                'sub_domain' => $client->sub_domain,
                'last_activity' => $client->last_activity ? Carbon::parse($client->last_activity)->toDateTimeString() : null,
                'is_active' => (bool) $client->is_active,
                'has_access' => (bool) ($hasAccess[$client->id] ?? false),
            ];
        }

        return $result;
    }

    private function totals(array $rows): array
    {
        $approved = array_filter($rows, fn ($r) => $r['approval_status'] === PartnerProgramService::STATUS_APPROVED);

        $sum = function (string $key) use ($rows): array {
            $out = [];
            foreach ($rows as $row) {
                foreach ($row[$key] as $code => $value) {
                    $out[$code] = round(($out[$code] ?? 0) + $value, 2);
                }
            }
            ksort($out);

            return $out;
        };

        $lights = array_fill_keys(self::LIGHTS, 0);
        $health = array_fill_keys(ClientHealthSnapshot::COLOR_ORDER, 0);
        foreach ($approved as $row) {
            if ($row['light']) {
                $lights[$row['light']]++;
            }
        }
        foreach ($rows as $row) {
            foreach ($row['health'] as $color => $count) {
                $health[$color] += $count;
            }
        }

        $byStatus = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $byStatus[$row['approval_status']] = ($byStatus[$row['approval_status']] ?? 0) + 1;
        }

        return [
            'partners' => count($rows),
            'by_status' => $byStatus,
            'with_sales' => count(array_filter($rows, fn ($r) => $r['connections'] + $r['renewals'] > 0 || $r['turnover_by_currency'] !== [])),
            'with_connections' => count(array_filter($rows, fn ($r) => $r['connections'] > 0)),
            'connections' => array_sum(array_column($rows, 'connections')),
            'renewals' => array_sum(array_column($rows, 'renewals')),
            'turnover_by_currency' => $sum('turnover_by_currency'),
            'turnover_uzs' => round(array_sum(array_column($rows, 'turnover_uzs')), 2),
            'commission_by_currency' => $sum('commission_by_currency'),
            'active_clients' => array_sum(array_column($rows, 'active_clients')),
            'lights' => $lights,
            'health' => $health,
            'nfr_total' => count(array_filter($approved, fn ($r) => $r['nfr'] !== null)),
            'nfr_active_7d' => count(array_filter($approved, fn ($r) => ($r['nfr']['last_activity'] ?? null)
                ? Carbon::parse($r['nfr']['last_activity'])->greaterThanOrEqualTo(now()->subDays(7))
                : false)),
        ];
    }
}
