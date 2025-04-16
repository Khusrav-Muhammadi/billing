<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\GetBalanceRequest;
use App\Http\Requests\Client\StoreRequest;
use App\Http\Requests\Client\TransactionRequest;
use App\Http\Requests\Client\UpdateRequest;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Pack;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Carbon\Carbon;
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
        $client = $client->load(['history.changes', 'history.user', 'tariff', 'country', 'partner']);
        $expirationDate = $this->calculateValidateDate($client);
        return response()->json([
            'organizations' => $organizations,
            'transactions' => $transactions,
            'businessTypes' => $businessTypes,
            'sales' => $sales,
            'tariffs' => $tariffs,
            'packs' => $packs,
            'client' => $client,
            'expirationDate' => $expirationDate,
        ]);
    }


    /**
     * Calculate validation date for a client
     *
     * @param Client $client
     * @return Carbon|null
     */
    protected function calculateValidateDate(Client $client)
    {

        if ($client->is_demo) {
            return Carbon::parse($client->created_at)->addWeeks(2);
        }


        $dailyPayment = round($this->calculateDailyPayment($client), 4);

        $days = (int)($client->balance / $dailyPayment);

        return Carbon::now()->addDays($days);
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
    protected function calculateDailyPayment(Client $client): float
    {
        if (!$client->tariff) {
            return 0;
        }


        $currentMonth = now();
        $daysInMonth = $currentMonth->daysInMonth;

        // Base daily payment from tariff
        $dailyPayment = $client->tariff->price / $daysInMonth;

        // Calculate additional daily cost from organization packs
        $packsDailyPayment = $client->organizations->sum(function ($organization) use ($daysInMonth) {
            return $organization->packs->sum(function ($organizationPack) use ($daysInMonth) {

                $pack = $organizationPack->pack()->first();


                return $pack ? ($pack->price / $daysInMonth) : 0;
            });
        });
        // Combine tariff and packs daily payment

        $totalDailyPayment = $dailyPayment + $packsDailyPayment;

        if ($client->sale_id) {
            $sale = $client->sale;

            if ($sale->sale_type === 'procent') {
                // Percentage discount on total daily payment
                $totalDailyPayment -= ($client->tariff->price * $sale->amount) / (100 * $daysInMonth);
            } else {
                // Fixed amount discount
                $totalDailyPayment -= $sale->amount / $daysInMonth;
            }
        }

        return max(0, $totalDailyPayment);
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
            'result' => Transaction::query()->where('client_id', $client->id)->with('tariff',  'sale', 'organization')->paginate(50),
        ]);
    }

    public function getPartners()
    {
        return response()->json([
            'result' => Partner::query()->paginate(50),
        ]);
    }

    public function sale()
    {
        return response()->json([
            'result' => Sale::query()->paginate(50),
        ]);
    }

    public function store(StoreRequest $request)
    {
        $this->repository->store($request->validated());
        return response()->json(['success' => true]);
    }


    public function getCountries()
    {
        return response()->json([
            'result' => Country::query()->paginate(50),
        ]);
    }

    public function getBusinessTypes()
    {
        return response()->json([
            'result' => BusinessType::query()->paginate(50),
        ]);
    }

    public function getHistory(Client $client)
    {
        return response()->json([
            'result' => $client->load(['history.changes', 'history.user']),
        ]);
    }


}
