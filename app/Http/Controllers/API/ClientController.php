<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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

class ClientController extends Controller
{

    public function __construct(public ClientRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        return response()->json([
            'clients' => $this->repository->getByPartner($request->all()),
        ]);
    }

    public function show(Client $client)
    {
        $organizations = Organization::where('client_id', $client->id)->get();
        $transactions = Transaction::where('client_id', $client->id)
            ->with(['tariff', 'client', 'organization', 'sale'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        $businessTypes = BusinessType::all();
        $sales = Sale::all();
        $tariffs = Tariff::all();
        $packs = Pack::all();
        $client = $client->load(['history.changes', 'history.user', 'tariff']);

        return response()->json([
            'organizations' => $organizations,
            'transactions' => $transactions,
            'businessTypes' => $businessTypes,
            'sales' => $sales,
            'tariffs' => $tariffs,
            'packs' => $packs,
            'client' => $client
        ]);
    }

    public function update(Client $client, UpdateRequest $request)
    {
        $this->repository->update($client, $request->validated());

        return response()->json(['success' => true]);
    }

    public function activation(Client $client)
    {
        $this->repository->activation($client);

        return response()->json(['success' => true]);
    }

    public function createTransaction(int $id, TransactionRequest $request)
    {
        $client = Client::find($id);
        $this->repository->createTransaction($client, $request->validated());
        return response()->json(['success' => true]);
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


    public function getOrganizations(Client $client)
    {
        return response()->json([
            'result' => Organization::query()->where('client_id', $client->id)->with('businessType')->paginate(10),
        ]);
    }

    public function getTransactions(Client $client)
    {
        return response()->json([
            'result' => Transaction::query()->where('client_id', $client->id)->with('tariff',  'sale')->paginate(50),
        ]);
    }



}
