<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Jobs\ChangeRequestStatusJob;
use App\Jobs\SubDomainJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\Organization;
use App\Models\PartnerRequest;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\WithdrawalService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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
            ->orderByDesc('created_at')
            ->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {

                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount;
                });
            });

            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            $client->total_users = $totalUsersFromOrganizations + $totalUsersFromPacks;

            $client->validate_date = $this->calculateValidateDate($client);
            return $client;
        });
        $clients->setCollection($processedClients);
        return $clients;
    }

    /**
     * Calculate daily payment for a client
     *
     * @param Client $client
     * @return float
     */
        protected function calculateDailyPayment(Client $client): float
        {
            if (!$client->tariff) {
                return 0;
            }

            $currentMonth = now();
            $daysInMonth = $currentMonth->daysInMonth;

            $dailyPayment = $client->tariff->price / $daysInMonth;

            $packsDailyPayment = $client->organizations->sum(function ($organization) use ($daysInMonth) {
                return $organization->packs->sum(function ($organizationPack) use ($daysInMonth) {

                    $pack = $organizationPack->pack()->first();


                    return $pack ? ($pack->price / $daysInMonth) : 0;
                });
            });

            $totalDailyPayment = $dailyPayment + $packsDailyPayment;

            if ($client->sale_id) {
                $sale = $client->sale;

                if ($sale->sale_type === 'procent') {
                    $totalDailyPayment -= ($client->tariff->price * $sale->amount) / (100 * $daysInMonth);
                } else {
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
                return Carbon::parse($client->created_at)->addWeeks(2)  ;
            }

            $dailyPayment = round($this->calculateDailyPayment($client), 4);

            $days = (int)($client->balance / $dailyPayment);

            return Carbon::now()->addDays($days);
        }

    public function store(array $data)
    {
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        if (isset($data['nfr']) && $data['nfr'] == 'on') $data['nfr'] = true;

        $client = Client::create($data);

        SubDomainJob::dispatch($client);

        if (isset($data['partner_request_id']) && $data['partner_request_id'] != null) {
            $partnerRequest = PartnerRequest::where('id', $data['partner_request_id'])->first();
            $partnerRequest->update(['request_status' => 'Успешный']);

            $partner = $partnerRequest->partner()->first();

            ChangeRequestStatusJob::dispatch($partner, $partnerRequest, Auth::user());
        }

        return $client;
    }

    public function update(Client $client, array $data)
    {
        if ($client->tariff_id != $data['tariff_id']) UpdateTariffJob::dispatch($client, $data['tariff_id'], $client->sub_domain);

        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        else $data['is_demo'] = false;

        $client->update($data);

        if ($data['is_demo'] == false) $this->withdrawal($client);

    }

    public function activation(Client $client, ?array $data)
    {
        $organizationIds = $client->organizations()->pluck('id')->toArray();
        $reject_cause = $data['reject_cause'] ?? '';

        ActivationJob::dispatch($organizationIds, $client->sub_domain, !$client->is_active, true, auth()->id() ?? 1, $reject_cause);
    }

    public function createTransaction(Client $client, array $data)
    {
        DB::transaction(function () use ($data, $client) {
            $organization = Organization::find($data['organization_id']);
            $data['type'] = 'Пополнение';
            $data['client_id'] = $client->id;
            Transaction::create($data);
            $organization->increment('balance', $data['sum']);
        });
    }

    public function getBalance(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        return response()->json([
            'balance' => $client->balance,
            'tariff' => $client->tariff->name
        ]);
    }

    public function withdrawal(Client $client)
    {
        $service = new WithdrawalService();
        $sum = $service->countSum($client);

        $organizations = $client->organizations()
            ->where('has_access', true)->get();

        foreach ($organizations as $organization) {
            $service->handle($organization, $sum);
        }

    }

    public function getByPartner(array $data)
    {
        $query = Client::query()->with(['tariff', 'country', 'partner'])->filter($data);

        if (auth()->user()->role == 'partner') {
            $query->where('partner_id', auth()->id());
        }

        $clients = $query->with(['sale', 'tariff', 'city', 'partner'])->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {

                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount ?? 0;
                });
            });


            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            $client->total_users = $totalUsersFromOrganizations + $totalUsersFromPacks;
            $client->validate_date = $this->calculateValidateDate($client);
            return $client;
        });

        $clients->setCollection($processedClients);

        return $clients;
    }

    public function countDifference(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        $organization = Organization::find($data['organization_id']);
        $newTariff = TariffCurrency::find($data['tariff_id']);
        $lastTariff = TariffCurrency::find($client->tariff_id);

        $licenseDifference = $newTariff->license_price > $lastTariff->license_price ? ($newTariff->license_price - $lastTariff->license_price) : 0;
        $tariffPrice = $newTariff->tariff_price * $data['month'];

        return [
            'organization_balance' => $organization->balance,
            'license_difference' => $licenseDifference,
            'tariff_price' => $tariffPrice
        ];
    }

    public function changeTariff(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();
        $newTariff = TariffCurrency::find($data['tariff_id']);
        $lastTariff = TariffCurrency::find($client->tariff_id);

        $organizations = $client->organizations;

        $currency = $client->currency;
        $exchangeRate = $currency->latestExchangeRate?->kurs ?? 1;

        if ($lastTariff->license_price < $newTariff->license_price) {
            $difference = $newTariff->license_price - $lastTariff->license_price;

            $amounts = $this->calculateAmounts($client, $difference, $currency, $exchangeRate);

            foreach ($organizations as $organization) {
                $organization->decrement('balance', $difference);
                $transactions = [
                    [
                        'sum' => $difference,
                        'accounted_amount' => $amounts['accounted_amount']
                    ]
                ];
                $this->createTransactions($client, $organization, $transactions);
            }
        }
    }

    private function calculateAmounts(Client $client, float $price, $currency, float $exchangeRate): array
    {
        $isUSD = $currency->symbol_code != 'USD';

        return [
            'accounted_amount' => $isUSD ? $price / $exchangeRate : $price,
        ];
    }

    private function createTransactions(Client $client, Organization $organization, array $transactions): void
    {
        foreach ($transactions as $transaction) {
            if ($transaction['sum'] > 0) {
                Transaction::create([
                    'client_id' => $client->id,
                    'organization_id' => $organization->id,
                    'tariff_id' => $client->tariff?->id,
                    'sale_id' => $client->sale?->id,
                    'sum' => $transaction['sum'],
                    'type' => 'Снятие',
                    'accounted_amount' => $transaction['accounted_amount']
                ]);
            }
        }
    }

}
