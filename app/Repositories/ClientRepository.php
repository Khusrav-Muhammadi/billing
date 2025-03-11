<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Jobs\ChangeRequestStatusJob;
use App\Jobs\SubDomainJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\Partner;
use App\Models\PartnerRequest;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\WithdrawalService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClientRepository implements ClientRepositoryInterface
{
    public function index(array $data): LengthAwarePaginator
    {
        $query = Client::query();

        $query = $query->filter($data);

        $clients = $query
            ->withCount('organizations')
            ->with(['tariff', 'partner', 'organizations.packs.pack' => function ($query) {
                $query->where('type', 'user');
            }])
            ->orderByDesc('created_at')
            ->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {

                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount ?? 0;
                });
            });


            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            // Общее количество пользователей
            $client->total_users = $totalUsersFromOrganizations + $totalUsersFromPacks;

            return $client;
        });

        $clients->setCollection($processedClients);
        return $clients;
    }

    public function store(array $data)
    {
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        if (isset($data['nfr']) && $data['nfr'] == 'on') $data['nfr'] = true;

        $client = Client::create($data);

        SubDomainJob::dispatch($client);

        if (isset($data['partner_request_id']) && $data['partner_request_id'] != null) {
            $partnerRequest = PartnerRequest::where('id', $data['partner_request_id'])->first();
            $partnerRequest->update(['request_status' => 'Успешный']);

            $partner = $partnerRequest->partner()->first();

            ChangeRequestStatusJob::dispatch($partner, $partnerRequest);
        }

        return $client;
    }

    public function update(Client $client, array $data)
    {
        if ($client->tariff_id != $data['tariff_id']) UpdateTariffJob::dispatch($client, $data['tariff_id'], $client->sub_domain);

        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        else $data['is_demo'] = false;

        $client->update($data);

        if ($data['is_demo'] == false) $this->withdrawal($client);

    }

    public function activation(Client $client, array $data)
    {

        $organizationIds = $client->organizations()->pluck('id')->toArray();
        $reject_cause = $data['reject_cause'] ?? '';

        ActivationJob::dispatch($organizationIds, $client->sub_domain, false, true, auth()->id(), $reject_cause);
    }

    public function createTransaction(Client $client, array $data)
    {
        DB::transaction(function () use ($data, $client) {
            $data['type'] = 'Пополнение';
            $data['client_id'] = $client->id;
            Transaction::create($data);
            $client->disableObserver = true;
            $client->increment('balance', $data['sum']);
        });
    }

    public function getBalance(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        return response()->json([
            'balance' => $client->balance,
            'tariff' => $client->tariff->name
        ]);
    }

    public function withdrawal(Client $client)
    {
        $service = new WithdrawalService();
        $sum = $service->countSum($client);

        $organizations = $client->organizations()
            ->where('has_access', true)->get();

        foreach ($organizations as $organization) {
            $service->handle($organization, $sum);
        }

    }

    public function getByPartner(array $data)
    {
        $clients = Client::query()->filter($data)->where('partner_id', auth()->id())->with(['tariff'])->paginate(20);
        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {

                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount ?? 0;
                });
            });


            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            // Общее количество пользователей
            $client->total_users = $totalUsersFromOrganizations + $totalUsersFromPacks;

            return $client;
        });

        $clients->setCollection($processedClients);

        return $clients;
    }
}
