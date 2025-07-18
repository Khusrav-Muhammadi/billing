<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Jobs\ChangeRequestStatusJob;
use App\Jobs\SubDomainJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\Organization;
use App\Models\PartnerRequest;
use App\Models\TariffCurrency;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\Billing\Enum\TransactionType;
use App\Services\Sale\SaleService;
use App\Services\WithdrawalService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClientRepository implements ClientRepositoryInterface
{
    public function __construct()
    {
    }

    public function index(array $data): LengthAwarePaginator
    {
        $query = Client::query();

        $query = $query->filter($data);

        $clients = $query
            ->withCount('organizations')
            ->with(['tariffPrice.tariff', 'partner', 'organizations.packs.pack' => function ($query) {
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
        if (!$client->tariffPrice) {
            return 0;
        }

        $currentMonth = now();
        $daysInMonth = $currentMonth->daysInMonth;

        $dailyPayment = $client->tariffPrice->tariff_price / $daysInMonth;

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
                $totalDailyPayment -= ($client->tariffPrice->tariff_price * $sale->amount) / (100 * $daysInMonth);
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
            return Carbon::parse($client->created_at)->addWeeks(2);
        }


        $dailyPayment = round($this->calculateDailyPayment($client), 4);

        if ($client->balance > 0) {
            $days = (int)($client->balance / $dailyPayment);
        } else {
            $days = 0;
        }

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
        $tariff_id = $client->country_id == 2 ? TariffCurrency::query()->where('tariff_id', $data['tariff_id'])->where('currency_id', $client->currency_id)->first()->id : $data['tariff_id'];

        if ($client->tariff_id != $tariff_id) UpdateTariffJob::dispatch($client, $tariff_id, $client->sub_domain);

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
        $organization = Organization::find($data['organization_id'] ?? null) ?? $client->organizations()->first();
        DB::transaction(function () use ($data, $client, $organization) {
            $data['type'] = TransactionType::CREDIT;
            $data['client_id'] = $client->id;
            $data['organization_id'] = $organization->id;
            Transaction::create($data);

            $organization->balance += $data['sum'];
            $organization->save();
        });
    }

    public function getBalance(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        $sum = 0;
        foreach ($client->organizations as $organization) {
            $sum += $organization->balance;
        }

        return response()->json([
            'balance' => $sum,
            'tariff' => $client->tariffPrice?->tariff?->name
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
        $query = Client::query()
            ->where('nfr', false)
            ->with(['tariffPrice.tariff', 'country', 'partner'])->filter($data);

        if (auth()->user()->role == 'partner') {
            $query->where('partner_id', auth()->id());
        }

        if (auth()->user()->role == 'manager') {
            $query->where('manager_id', auth()->id());
        }

        $clients = $query->with(['sale', 'tariffPrice.tariff', 'city', 'partner', 'manager'])->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {
                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount ?? 0;
                });
            });

            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            $organizations = $client->organizations;
            $balance = 0;

            foreach ($organizations as $organization) {
                $balance += $organization->balance;
            }

            $currency = $client->currency;

            $client->balance = $balance;

            $client->total_users = $totalUsersFromOrganizations + $totalUsersFromPacks;
            $client->validate_date = $this->calculateValidateDate($client);

            $client->balance = "$balance $currency?->symbol_code";

            return $client;
        });

        $clients->setCollection($processedClients);
        return $clients;
    }

    public function getNfr(array $data)
    {
        $query = Client::query()
            ->where('nfr', true)
            ->with(['tariffPrice.tariff', 'country', 'partner'])
            ->filter($data);

        if (auth()->user()->role == 'partner') {
            $query->where('partner_id', auth()->id());
        }

        $clients = $query->with(['sale', 'tariffPrice.tariff', 'city', 'partner'])->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {
                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount ?? 0;
                });
            });

            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            $organizations = $client->organizations;
            $balance = 0;

            foreach ($organizations as $organization) {
                $balance += $organization->balance;
            }

            $client->balance = (string)$balance;
            $client->save();

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
        $newTariff = TariffCurrency::query()->where('currency_id', $client->currency_id)->where('id', $data['tariff_id'])->first();
        $lastTariff = TariffCurrency::query()->where('currency_id', $client->currency_id)->where('id', $client->tariff_id)->first();

//        $licenseDifference = $newTariff->license_price > $lastTariff->license_price ? ($newTariff->license_price - $lastTariff->license_price) : 0;
        $tariffPrice = $newTariff->tariff_price;
        $tariffPriceByMonth = $newTariff->tariff_price * $data['month'];
//        $licensePrice = $newTariff->license_price;

        $saleService = new SaleService();
        $activeSales = $saleService->getActiveSales();

//        $saleLicensePrice = 0;
        $saleTariffPrice = 0;

        foreach ($activeSales as $activeSale) {
            if ($activeSale->min_months != $data['month']) {
                continue;
            }
            if ($activeSale->apply_to == 'progressive') {
                $saleTariffPrice = $tariffPrice * ($activeSale->amount / 100) * $data['month'];
            } else {
//                $saleLicensePrice = $licensePrice * ($activeSale->amount / 100);
            }
        }

        $implementation = $organization->implementation_sum ?? 0;

//        $licenseForPay = $licensePrice - $saleLicensePrice - $organization->sum_paid_for_license;
//        $licenseForPay = max($licenseForPay, 0);
        $tariffForPay = $tariffPriceByMonth - $saleTariffPrice;
        if ($data['type'] == 'tariff_renewal') $sumForPay = $tariffForPay;
//        else $sumForPay = $organization->balance - $licenseForPay - $tariffForPay;
        else $sumForPay = $organization->balance - $implementation - $tariffForPay;

        return [
            'organization_balance' => $organization->balance,
//            'license_difference' => $licenseDifference,
//            'license_price' => $licensePrice,
//            'sale_license_price' => round($saleLicensePrice, 2),
            'tariff_price' => $tariffPrice,
            'tariff_price_by_month' => $tariffPriceByMonth,
            'sale_tariff_price' => round($saleTariffPrice, 2),
            'must_pay' => false, //$difference < 0,
//            'upgrade' => $organization->sum_paid_for_license,
//            'license_for_pay' => $licenseForPay,
            'tariff_for_pay' => $tariffForPay,
            'sum_for_pay' => $sumForPay < 0 ? abs(round($sumForPay, 2)) : 0,
            'currency' => $client->currency,
            'implementation' => $implementation
        ];
    }

    public function changeTariff(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        $organization = Organization::find($data['organization_id']);

        $client->update(['tariff_id' => $data['tariff_id']]);

        $service = new WithdrawalService();
        $tariffSum = $service->countSum($client);
        $service->handle($organization, $tariffSum);
    }

}
