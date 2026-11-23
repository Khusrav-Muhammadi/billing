<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientHealthSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Индекс здоровья клиентов для админки партнёрского портала (middleware partners.token).
 * Список — на последний посчитанный день, красные сверху.
 */
class ClientHealthController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'color' => ['nullable', Rule::in(ClientHealthSnapshot::COLOR_ORDER)],
            'type' => ['nullable', Rule::in(['paid', 'demo'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            // Партнёрский кабинет: только клиенты партнёра (clients.partner_id). Портал сам решает, чей это id.
            'partner_id' => ['nullable', 'integer', 'min:1'],
            // Строки только для этих клиентов (колонка «Здоровье» в списке клиентов).
            'client_ids' => ['nullable', 'array', 'max:500'],
            'client_ids.*' => ['integer', 'min:1'],
        ]);

        $date = ClientHealthSnapshot::query()->max('date');
        if ($date === null) {
            return response()->json(['date' => null, 'counts' => $this->emptyCounts(), 'rows' => [], 'meta' => ['total' => 0, 'page' => 1, 'last_page' => 1, 'per_page' => 50]]);
        }

        $base = ClientHealthSnapshot::query()
            ->whereDate('date', $date)
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('client_type', $type))
            ->when($data['partner_id'] ?? null, fn ($q, $partnerId) => $q->whereHas('client', fn ($c) => $c->where('partner_id', $partnerId)))
            ->when($data['client_ids'] ?? null, fn ($q, $ids) => $q->whereIn('client_id', array_map('intval', $ids)))
            ->when(trim((string) ($data['search'] ?? '')), function ($q, $search) {
                $q->whereHas('client', fn ($c) => $c->where(fn ($w) => $w
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sub_domain', 'like', '%' . $search . '%')));
            });

        $counts = $this->emptyCounts();
        foreach ((clone $base)->selectRaw('color, COUNT(*) AS c')->groupBy('color')->pluck('c', 'color') as $color => $c) {
            $counts[$color] = (int) $c;
        }

        $order = implode(',', array_map(fn ($c) => "'" . $c . "'", ClientHealthSnapshot::COLOR_ORDER));
        $page = (clone $base)
            ->when($data['color'] ?? null, fn ($q, $color) => $q->where('color', $color))
            ->with(['client:id,name,sub_domain,partner_id,manager_id', 'client.partner:id,name', 'client.manager:id,name'])
            ->orderByRaw("FIELD(color, {$order})")
            ->orderBy('health_index')
            ->orderBy('client_id')
            ->paginate((int) ($data['per_page'] ?? 50));

        return response()->json([
            'date' => (string) $date,
            'counts' => $counts,
            'rows' => collect($page->items())->map(fn (ClientHealthSnapshot $s) => $this->row($s))->values(),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    public function history(Request $request, Client $client): JsonResponse
    {
        $days = max(7, min(180, (int) $request->query('days', 60)));
        $client->loadMissing(['partner:id,name', 'manager:id,name']);

        $rows = ClientHealthSnapshot::query()
            ->where('client_id', $client->id)
            ->whereDate('date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('date')
            ->get()
            ->map(fn (ClientHealthSnapshot $s) => $this->row($s, withMetrics: true) + ['reasons' => $s->reasons ?? []])
            ->values();

        return response()->json([
            'client' => [
                'id' => (int) $client->id,
                'name' => $client->name,
                'sub_domain' => $client->sub_domain,
                'crm_url' => $client->sub_domain ? $client->crmUrl() : null,
                'partner' => $client->partner ? ['id' => (int) $client->partner->id, 'name' => $client->partner->name] : null,
                'manager' => $client->manager ? ['id' => (int) $client->manager->id, 'name' => $client->manager->name] : null,
            ],
            'rows' => $rows,
        ]);
    }

    private function row(ClientHealthSnapshot $s, bool $withMetrics = false): array
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
                'deals' => $s->deals_score,
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
                'weeks' => $m['weeks'] ?? [],
            ];
        }

        return $row;
    }

    private function emptyCounts(): array
    {
        return array_fill_keys(ClientHealthSnapshot::COLOR_ORDER, 0);
    }
}
