<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Jobs\SubDomainJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ClientRepository implements ClientRepositoryInterface
{

    public function index()
    {
        return Client::all();
    }

    public function store(array $data)
    {
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;

        Client::create($data);

        SubDomainJob::dispatch($data['sub_domain']);
    }

    public function update(Client $client, array $data)
    {
        if ($client->tariff_id != $data['tariff_id']) UpdateTariffJob::dispatch($client, $data['tariff_id'], $client->back_sub_domain);

        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        else $data['is_demo'] = false;

        $client->update($data);
    }

    public function activation(Client $client)
    {
        $organizationIds = $client->organizations()->pluck('id')->toArray();

        ActivationJob::dispatch($organizationIds, $client->sub_domain, !$client->is_active);

        $client->update(['is_active' => !$client->is_active]);
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
