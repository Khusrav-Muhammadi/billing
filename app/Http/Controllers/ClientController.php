<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\GetBalanceRequest;
use App\Http\Requests\Client\RejectRequest;
use App\Http\Requests\Client\StoreRequest;
use App\Http\Requests\Client\TransactionRequest;
use App\Http\Requests\Client\UpdateRequest;
use App\Jobs\SubDomainJob;
use App\Models\BusinessType;
use App\Models\Client;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Pack;
use App\Models\Partner;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\OrganizationRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $countries = Country::get();

        return view('admin.clients.create', compact( 'tariffs', 'sales', 'partners', 'countries'));
    }

    public function store(StoreRequest $request)
    {
        $data = $request->validated();
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;

        $client = $this->createDemoClient($data);

        SubDomainJob::dispatch($client);

        $data = [
            'name' => $client->name,
            'phone' => $client->phone,
            'client_id' => $client->id,
            'has_access' => true
        ];


        (new OrganizationRepository())->store($client, $data);

        return redirect()->route('client.index');
    }
    private function generateSubdomain(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $isPublic = in_array(strtolower($domain), config('app.public_domains'));

        return Str::of($isPublic ? $local : $local . $domain)
            ->replace('_', '')
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->trim('-')
            ->replaceMatches('/-+/', '-')
            ->whenEmpty(fn() => 'default');
    }
    private function createDemoClient(array $data): ?Client
    {
        $clientData = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'country_id' => $data['country_id'] ?? 1,
            'is_demo' => true,
            'tariff_id' => $data['tariff_id'],
            'sub_domain' => $this->generateSubdomain($data['email'])
        ];

        $client = Client::query()->where('sub_domain', $clientData['sub_domain'])->orWhere('phone',$clientData['phone'])->first();
        if ($client) return null;


        return Client::create($clientData);
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
        $countries = Country::all();
        $client = $client->load(['history.changes', 'history.user']);
        $partners = User::query()->where('role', 'partner')->get();

        $expirationDate = $this->calculateValidateDate($client);

        return view('admin.clients.show', compact('client', 'organizations', 'transactions', 'businessTypes', 'packs', 'tariffs', 'sales', 'partners', 'expirationDate', 'countries'));
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
