<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\GetBalanceRequest;
use App\Http\Requests\Client\RejectRequest;
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
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Http\Request;

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
        $sales = Sale::all();
        $tariffs = Tariff::all();
        $partners = User::query()->where('role', 'partner')->get();

        return view('admin.clients.create', compact( 'tariffs', 'sales', 'partners'));
    }

    public function store(StoreRequest $request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('client.index');
    }

    public function show(Client $client)
    {
        $organizations = Organization::where('client_id', $client->id)->get();
        $transactions = Transaction::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        $businessTypes = BusinessType::all();
        $sales = Sale::all();
        $tariffs = Tariff::all();
        $packs = Pack::all();
        $client = $client->load(['history.changes', 'history.user']);
        $partners = Partner::all();

        return view('admin.clients.show', compact('client', 'organizations', 'transactions', 'businessTypes', 'packs', 'tariffs', 'sales', 'partners'));
    }

    public function update(Client $client, UpdateRequest $request)
    {
        $this->repository->update($client, $request->validated());

        return redirect()->route('client.index');
    }

    public function activation(Client $client, RejectRequest $request)
    {
        $this->repository->activation($client, $request->validated());

        return redirect()->back();
    }

    public function createTransaction(Client $client, TransactionRequest $request)
    {
        $this->repository->createTransaction($client, $request->validated());
        return redirect()->back();
    }

    public function getBalance(GetBalanceRequest $request)
    {
        return $this->repository->getBalance($request->validated());
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
