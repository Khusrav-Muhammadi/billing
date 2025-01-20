<?php

namespace App\Repositories;


use App\Jobs\DeleteOrganizationJob;
use App\Jobs\SubDomainJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
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
            ->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {
                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->pack->amount ?? 0;
                });
            });

            $client->total_users = ($client->tariff->user_count ?? 0) + $totalUsersFromPacks;

            return $client;
        });

        // Обновляем коллекцию в существующем объекте пагинации
        $clients->setCollection($processedClients);

        return $clients;
    }




    public function store(array $data)
    {
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;

        $client = Client::create($data);

        SubDomainJob::dispatch($client);

        return $client;
    }

    public function update(Client $client, array $data)
    {
        if ($client->tariff_id != $data['tariff_id']) UpdateTariffJob::dispatch($client, $data['tariff_id'], $client->back_sub_domain);

        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        else $data['is_demo'] = false;

        $client->update($data);
    }

    public function destroy(Client $client)
    {
        $organizations = $client->organizations()->get();

        foreach ($organizations as $organization) {
            DeleteOrganizationJob::dispatch($organization, $client->sub_domain);
            $organization->update(['has_access' => false]);
        }

        $client->update(['is_active' => false]);
    }

    public function createTransaction(Client $client, array $data)
    {
        DB::transaction(function () use ($data, $client) {
            $data['type'] = 'Пополнение';
            $data['client_id'] = $client->id;
            Transaction::create($data);
            $client->increment('balance', $data['sum']);
        });

    }

    public function getBalance(array $data)
    {
        return Client::where('sub_domain', $data['sub_domain'])->first()->balance;
    }
}
