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
use Illuminate\Support\Carbon;

class DashBoardController extends Controller
{

    public function __construct(public ClientRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        $clients = Client::query()
            ->selectRaw('
        SUM(CASE WHEN clients.is_demo = 0 THEN 1 ELSE 0 END) as real_clients,
        SUM(CASE WHEN clients.is_demo = 1 THEN 1 ELSE 0 END) as demo_clients
    ')
            ->first();

        $clientsActivity = Client::query()
            ->selectRaw('
        DATE_FORMAT(created_at, "%Y-%m") as month,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_clients,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_clients
    ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $activeClientsByMonth = array_fill(0, 12, 0);
        $inactiveClientsByMonth = array_fill(0, 12, 0);

        foreach ($clientsActivity as $activity) {
            $month = (int)date('m', strtotime($activity->month)) - 1;
            $activeClientsByMonth[$month] = (int)$activity->active_clients;
            $inactiveClientsByMonth[$month] = (int)$activity->inactive_clients;
        }

        $tariffs = Tariff::all();
        $incomeData = [];

        foreach ($tariffs as $tariff) {
            $incomeData[$tariff->name] = [];

            for ($month = 1; $month <= 12; $month++) {
                $clientCount = Client::where('tariff_id', $tariff->id)
                    ->whereMonth('created_at', $month)
                    ->count();
                $income = $clientCount * $tariff->price;

                $incomeData[$tariff->name][] = $income;
            }
        }
        $chartData = [];

        foreach ($incomeData as $tariffName => $income) {
            $chartData[] = [
                'name' => $tariffName,
                'data' => $income,
            ];
        }

        $partners = Partner::all();
        $totalIncomeFromPartners = 0;

        foreach ($partners as $partner) {
            $clientss = Client::where('partner_id', $partner->id)->get();

            foreach ($clientss as $client) {
                $tariff = Tariff::find($client->tariff_id);
                if ($tariff) {
                    $totalIncomeFromPartners += $tariff->price;
                }
            }
        }


        $clients_count = Client::query()->where('is_active', 1)->count();


        $totalIncomeForMonth = 0;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $clientsss = Client::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->get();

        foreach ($clientsss as $client) {
            $tariff = Tariff::find($client->tariff_id);
            if ($tariff) {
                $totalIncomeForMonth += $tariff->price;
            }
        }

        $partners = Partner::count();

        return view('dashboard', compact('clients',  'activeClientsByMonth', 'inactiveClientsByMonth', 'chartData', 'clients_count', 'totalIncomeFromPartners', 'totalIncomeForMonth', 'partners'));
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
        $sales = Sale::all();
        $tariffs = Tariff::all();
        $packs = Pack::all();
        $client = $client->load(['history.changes', 'history.user']);

        return view('admin.clients.show', compact('client', 'organizations', 'transactions', 'businessTypes', 'packs', 'tariffs', 'sales'));
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
