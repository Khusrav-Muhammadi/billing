<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientHealthSnapshot;
use App\Models\Organization;
use App\Models\User;
use App\Services\ClientHealth\ClientHealthReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientHealthController extends Controller
{
    public function __construct(private readonly ClientHealthReadService $health)
    {
    }

    public function index(Request $request): View
    {
        $color = in_array($request->query('color'), ClientHealthSnapshot::COLOR_ORDER, true) ? $request->query('color') : null;
        $type = in_array($request->query('type'), ['paid', 'demo'], true) ? $request->query('type') : null;
        $search = trim((string) $request->query('search', ''));
        $partner = $request->query('partner') === 'any'
            ? 'any'
            : ((int) $request->query('partner') > 0 ? (string) (int) $request->query('partner') : null);
        $partnerId = $partner !== null && $partner !== 'any' ? (int) $partner : null;

        $filters = [
            'color' => $color,
            'type' => $type,
            'search' => $search,
            'partner_id' => $partnerId,
            'with_partner' => $partner === 'any',
        ];

        $page = $this->health->list($filters);
        $rows = $page->through(fn (ClientHealthSnapshot $s) => $this->health->present($s));

        $clientIds = collect($rows->items())->pluck('client_id')->filter()->all();
        $orgByClient = $clientIds === []
            ? collect()
            : Organization::query()->whereIn('client_id', $clientIds)->pluck('id', 'client_id');

        return view('admin.health.index', [
            'rows' => $rows,
            'counts' => $this->health->counts($partnerId, $type, [
                'search' => $search,
                'with_partner' => $partner === 'any',
            ]),
            'date' => $this->health->latestDate(),
            'color' => $color,
            'type' => $type,
            'search' => $search,
            'partner' => $partner,
            'partnerOptions' => User::query()->where('role', 'partner')->orderBy('name')->pluck('name', 'id'),
            'orgByClient' => $orgByClient,
            'error' => null,
        ]);
    }

    public function show(Client $client): View
    {
        $client->loadMissing(['partner:id,name', 'manager:id,name']);
        $rows = $this->health->history($client, 60)
            ->map(fn (ClientHealthSnapshot $s) => $this->health->present($s, true));

        return view('admin.health.show', [
            'client' => [
                'id' => (int) $client->id,
                'name' => $client->name,
                'sub_domain' => $client->sub_domain,
                'crm_url' => $client->sub_domain ? $client->crmUrl() : null,
                'partner' => $client->partner ? ['id' => (int) $client->partner->id, 'name' => $client->partner->name] : null,
                'manager' => $client->manager ? ['id' => (int) $client->manager->id, 'name' => $client->manager->name] : null,
            ],
            'rows' => $rows,
            'error' => null,
        ]);
    }
}
