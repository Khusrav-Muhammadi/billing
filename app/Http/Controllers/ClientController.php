<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\StoreRequest;
use App\Http\Requests\Client\UpdateRequest;
use App\Jobs\SubDomainJob;
use App\Jobs\UpdateTariffJob;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Pack;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::all();

        return view('admin.clients.index', compact('clients'));
    }

    public function create()
    {
        $businessTypes = BusinessType::all();
        $sales = Sale::all();
        $tariffs = Tariff::all();

        return view('admin.clients.create', compact('businessTypes', 'tariffs', 'sales'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Client::create($data);

        SubDomainJob::dispatch($data['sub_domain']);

        return redirect()->route('client.index');
    }

    public function show(Client $client)
    {
        $organizations = Organization::where('client_id', $client->id)->get();
        $transactions = Transaction::where('client_id', $client->id)->get();
        $businessTypes = BusinessType::all();
        $packs = Pack::all();

        return view('admin.clients.show', compact('client', 'organizations', 'transactions', 'businessTypes', 'packs'));
    }

    public function edit(Client $client)
    {
        $businessTypes = BusinessType::all();
        $sales = Sale::all();
        $tariffs = Tariff::all();

        return view('admin.clients.edit', compact('client', 'businessTypes', 'sales', 'tariffs'));
    }

    public function update(Client $client, UpdateRequest $request)
    {
        $data = $request->validated();

        if ($client->tariff_id != $data['tariff_id']) UpdateTariffJob::dispatch($client, $data['tariff_id'], $client->back_sub_domain);

        $client->update($data);

        return redirect()->route('client.index');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->back();
    }
}
