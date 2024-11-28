<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\StoreRequest;
use App\Http\Requests\Client\UpdateRequest;
use App\Jobs\SubDomainJob;
use App\Models\BusinessType;
use App\Models\Client;
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
        return view('admin.clients.create', compact('businessTypes'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        Client::create($data);

        SubDomainJob::dispatch($data['sub_domain']);

        return redirect()->route('client.index');
    }

    public function edit(Client $client)
    {
        $businessTypes = BusinessType::all();

        return view('admin.clients.edit', compact('client', 'businessTypes'));
    }

    public function update(Client $client, UpdateRequest $request)
    {
        $data = $request->validated();

        $client->update($data);

        return redirect()->route('client.index');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->back();
    }
}
