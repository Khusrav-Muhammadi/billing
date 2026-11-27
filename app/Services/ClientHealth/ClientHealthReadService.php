<?php

namespace App\Services\ClientHealth;

use App\Models\Client;
use App\Models\ClientHealthSnapshot;
use App\Models\Organization;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Чтение снимков светофора для админки. Расчёт (ClientHealthCalculator / ClientHealthService) не трогает.
 */
class ClientHealthReadService
{
    private bool $latestDateResolved = false;

    private ?string $latestDateMemo = null;

    public function latestDate(): ?string
    {
        if (!$this->latestDateResolved) {
            $date = ClientHealthSnapshot::query()->max('date');
            $this->latestDateMemo = $date !== null && $date !== '' ? (string) $date : null;
            $this->latestDateResolved = true;
        }

        return $this->latestDateMemo;
    }

    /**
     * @param  array{search?: string, with_partner?: bool}  $extra
     * @return array{red: int, yellow: int, grey: int, green: int}
     */
    public function counts(?int $partnerId = null, ?string $type = 'paid', array $extra = []): array
    {
        $counts = $this->emptyCounts();
        $date = $this->latestDate();
        if ($date === null) {
            return $counts;
        }

        $query = $this->base($date, $type, $partnerId, $extra);
        foreach ($query->selectRaw('color, COUNT(*) AS c')->groupBy('color')->pluck('c', 'color') as $color => $c) {
            if (array_key_exists($color, $counts)) {
                $counts[$color] = (int) $c;
            }
        }

        return $counts;
    }

    /**
     * @param  array<int>  $clientIds
     * @return array<int, ClientHealthSnapshot>
     */
    public function forClients(array $clientIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $clientIds))));
        $date = $this->latestDate();
        if ($ids === [] || $date === null) {
            return [];
        }

        return ClientHealthSnapshot::query()
            ->whereDate('date', $date)
            ->whereIn('client_id', $ids)
            ->get()
            ->keyBy(fn (ClientHealthSnapshot $s) => (int) $s->client_id)
            ->all();
    }

    /**
     * @return array<int, array{red: int, yellow: int, grey: int, green: int}>
     */
    public function countsByPartner(?string $type = 'paid'): array
    {
        $date = $this->latestDate();
        if ($date === null) {
            return [];
        }

        $rows = ClientHealthSnapshot::query()
            ->whereDate('client_health_snapshots.date', $date)
            ->when($type, fn ($q) => $q->where('client_health_snapshots.client_type', $type))
            ->join('clients', 'clients.id', '=', 'client_health_snapshots.client_id')
            ->whereNotNull('clients.partner_id')
            ->groupBy('clients.partner_id', 'client_health_snapshots.color')
            ->selectRaw('clients.partner_id AS pid, client_health_snapshots.color AS color, COUNT(*) AS c')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $pid = (int) $row->pid;
            $out[$pid] ??= $this->emptyCounts();
            if (array_key_exists($row->color, $out[$pid])) {
                $out[$pid][$row->color] = (int) $row->c;
            }
        }

        return $out;
    }

    /**
     * Активные клиенты партнёра: организации с действующим подключением.
     * Тот же запрос, что в API\PartnerProgramController::index.
     *
     * @return array<int, int>
     */
    public function activeClientsByPartner(): array
    {
        return Organization::query()
            ->whereHas('latestConnection', fn ($q) => $q->where('status', 'connected'))
            ->join('clients', 'clients.id', '=', 'organizations.client_id')
            ->whereNotNull('clients.partner_id')
            ->groupBy('clients.partner_id')
            ->selectRaw('clients.partner_id AS pid, COUNT(DISTINCT organizations.client_id) AS c')
            ->pluck('c', 'pid')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /**
     * @param  array{color?: ?string, type?: ?string, search?: ?string, partner_id?: ?int, with_partner?: bool}  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $date = $this->latestDate();
        $perPage = 50;
        if ($date === null) {
            return new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);
        }

        $order = implode(',', array_map(fn ($c) => "'" . $c . "'", ClientHealthSnapshot::COLOR_ORDER));

        return $this->base($date, $filters['type'] ?? null, isset($filters['partner_id']) ? (int) $filters['partner_id'] : null, [
            'search' => $filters['search'] ?? null,
            'with_partner' => !empty($filters['with_partner']),
        ])
            ->when($filters['color'] ?? null, fn ($q, $color) => $q->where('color', $color))
            ->with(['client:id,name,sub_domain,partner_id,manager_id', 'client.partner:id,name', 'client.manager:id,name'])
            ->orderByRaw("FIELD(color, {$order})")
            ->orderBy('health_index')
            ->orderBy('client_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Доля работающих клиентов по дням ночного расчёта.
     * Работают — зелёные и жёлтые. 100% — все клиенты, у которых в этот день есть снимок.
     *
     * @return list<array{date: string, total: int, working: int, percent: float, delta: ?float, direction: ?string, colors: array{red: int, yellow: int, grey: int, green: int}}>
     */
    public function activitySeries(?string $type, int $days): array
    {
        $days = max(1, min(120, $days));
        $from = now()->startOfDay()->subDays($days - 1)->toDateString();

        $rows = ClientHealthSnapshot::query()
            ->when($type, fn ($q) => $q->where('client_type', $type))
            ->where('date', '>=', $from)
            ->select('date', 'color')
            ->selectRaw('COUNT(*) as clients')
            ->groupBy('date', 'color')
            ->orderBy('date')
            ->toBase()
            ->get();

        return (new ClientActivitySummary())->series($rows->map(fn ($row) => [
            'date' => substr((string) $row->date, 0, 10),
            'color' => (string) $row->color,
            'count' => (int) $row->clients,
        ]));
    }

    public function history(Client $client, int $days = 60): Collection
    {
        return ClientHealthSnapshot::query()
            ->where('client_id', $client->id)
            ->whereDate('date', '>=', now()->subDays($days)->toDateString())
            ->with(['client:id,name,sub_domain,partner_id,manager_id', 'client.partner:id,name', 'client.manager:id,name'])
            ->orderByDesc('date')
            ->get();
    }

    public function present(ClientHealthSnapshot $s, bool $withMetrics = false): array
    {
        $client = $s->client;
        $daysLeft = $s->paid_until ? (int) $s->date->diffInDays($s->paid_until, false) : null;

        $row = [
            'client_id' => (int) $s->client_id,
            'name' => $client?->name,
            'sub_domain' => $client?->sub_domain,
            'date' => $s->date->toDateString(),
            'client_type' => $s->client_type,
            'color' => $s->color,
            'health_index' => (int) $s->health_index,
            'scores' => [
                'logins' => $s->logins_score,
                'coverage' => $s->coverage_score,
                'dialogs' => $s->dialogs_score,
                'entities' => $s->entities_score,
            ],
            'reason' => $s->reason_text,
            'paid_until' => $s->paid_until?->toDateString(),
            'days_left' => $daysLeft,
            'partner' => $client?->partner ? ['id' => (int) $client->partner->id, 'name' => $client->partner->name] : null,
            'manager' => $client?->manager ? ['id' => (int) $client->manager->id, 'name' => $client->manager->name] : null,
        ];

        if ($withMetrics) {
            $m = $s->metrics ?? [];
            $row['metrics'] = [
                'users_total' => (int) ($m['users_total'] ?? 0),
                'active_users' => (int) data_get($m, 'activity.active_users', 0),
                'active_user_days' => (int) data_get($m, 'activity.active_user_days', 0),
            ];
            $row['reasons'] = $s->reasons ?? [];
        }

        return $row;
    }

    private function base(string $date, ?string $type, ?int $partnerId, array $extra)
    {
        $search = trim((string) ($extra['search'] ?? ''));

        return ClientHealthSnapshot::query()
            ->whereDate('date', $date)
            ->when($type, fn ($q) => $q->where('client_type', $type))
            ->when($partnerId, fn ($q) => $q->whereHas('client', fn ($c) => $c->where('partner_id', $partnerId)))
            ->when(!empty($extra['with_partner']), fn ($q) => $q->whereHas('client', fn ($c) => $c->whereNotNull('partner_id')))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('client', fn ($c) => $c->where(fn ($w) => $w
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sub_domain', 'like', '%' . $search . '%')));
            });
    }

    private function emptyCounts(): array
    {
        return array_fill_keys(ClientHealthSnapshot::COLOR_ORDER, 0);
    }
}
