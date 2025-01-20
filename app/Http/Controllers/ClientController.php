<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\GetBalanceRequest;
use App\Http\Requests\Client\StoreRequest;
use App\Http\Requests\Client\TransactionRequest;
use App\Http\Requests\Client\UpdateRequest;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Pack;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ClientController extends Controller
{

    public function __construct(public ClientRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        $clients = $this->repository->index($request->all());
        $partners = Partner::all();
        $tariffs = Tariff::all();

        if ($request->ajax()) {
            return view('admin.partials.clients', compact('clients'))->render();
        }
        return view('admin.clients.index', compact('clients', 'partners', 'tariffs'));
    }

    public function create()
    {
        $businessTypes = BusinessType::all();
        $sales = Sale::all();
        $tariffs = Tariff::all();
        $partners = Partner::all();

        return view('admin.clients.create', compact('businessTypes', 'tariffs', 'sales', 'partners'));
    }

    public function store(StoreRequest $request)
    {

        $this->repository->store($request->validated());

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
        $this->repository->update($client, $request->validated());

        return redirect()->route('client.index');
    }

    public function activation(Client $client)
    {
        $this->repository->activation($client);

        return redirect()->back();
    }

    public function createTransaction(Client $client, TransactionRequest $request)
    {
        $this->repository->createTransaction($client, $request->validated());
        return redirect()->back();
    }

    public function getBalance(GetBalanceRequest $request)
    {
        $balance = $this->repository->getBalance($request->validated());

        return response()->json([
            'balance' => $balance
        ]);
    }

    public function updateActivity(Request $request, string $subdomain)
    {
        $client = Client::query()->firstWhere('sub_domain', $subdomain);
        if (!$client) {
            abort(404);
        }

        $client->last_activity = $request->last_activity;
        $client->save();

        return response()->json([
            'result' => 'success'
        ]);
    }
}
