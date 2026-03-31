<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Jobs\CreateOrganizationJob;
use App\Jobs\SendOrganizationLicense;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Price;
use App\Models\Transaction;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    private function getTariffMonthlyPriceFromPriceList(Client $client, Carbon $asOf): float
    {
        $currencyId = (int) ($client->currency_id ?? 0);
        if ($currencyId <= 0) return 0.0;

        // Client::tariff_id points to TariffCurrency in some flows, so prefer the base tariff id from tariffPrice.
        $baseTariffId = (int) ($client->tariffPrice?->tariff_id ?? 0);
        if ($baseTariffId <= 0) {
            $baseTariffId = (int) ($client->tariff_id ?? 0);
        }
        if ($baseTariffId <= 0) return 0.0;

        $date = $asOf->toDateString();

        $price = Price::query()
            ->where('tariff_id', $baseTariffId)
            ->where('currency_id', $currencyId)
            ->where('kind', 'base')
            ->whereNull('organization_id') // общая цена
            ->where(function ($q) use ($date) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('date')->orWhere('date', '>=', $date);
            })
            ->orderByRaw("COALESCE(start_date, '0000-00-00') DESC")
            ->orderByRaw("COALESCE(date, '9999-12-31') DESC")
            ->orderByDesc('id')
            ->first();

        return (float) ($price?->sum ?? 0);
    }

    private function extractClientFilters(array $data): array
    {
        $clientFilters = $data;
        unset($clientFilters['search']);

        return $clientFilters;
    }

    private function applyOrganizationSearch(Builder $query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $searchTerm = '%' . $search . '%';
        $digits = preg_replace('/\D+/', '', $search);

        $query->where(function (Builder $builder) use ($searchTerm, $digits) {
            $builder
                ->where('name', 'like', $searchTerm)
                ->orWhere('phone', 'like', $searchTerm)
                ->orWhere('email', 'like', $searchTerm)
                ->orWhereHas('client', function (Builder $clientQuery) use ($searchTerm) {
                    $clientQuery
                        ->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('phone', 'like', $searchTerm)
                        ->orWhere('sub_domain', 'like', $searchTerm);
                });

            if ($digits !== '') {
                $builder->orWhere('order_number', 'like', '%' . $digits . '%');

                if (strlen($digits) < 9) {
                    $builder->orWhere('order_number', 'like', '%' . Organization::formatOrderNumber((int) $digits) . '%');
                }
            }
        });
    }

    private function organizationsQuery(array $clientIds, array $data): Builder
    {
        $query = Organization::query()
            ->with(['client.tariffPrice.tariff', 'client.partner'])
            ->whereIn('client_id', $clientIds);

        $this->applyOrganizationSearch($query, $data['search'] ?? null);

        return $query->orderBy('id');
    }

    public function index(array $data)
    {
        $query = Client::query()->where(function (Builder $builder) {
            $builder->whereHas('transactions');
        });

        $clientIds = $query->filter($this->extractClientFilters($data))->pluck('id')->toArray();

        return $this->organizationsQuery($clientIds, $data)->get();
    }

    public function demo(array $data)
    {
        $query = Client::query()->doesntHave('transactions')->where('nfr', false);

        $clientIds = $query->filter($this->extractClientFilters($data))->pluck('id')->toArray();

        return $this->organizationsQuery($clientIds, $data)->get();
    }

    public function store(Client $client, array $data)
    {
        $data['client_id'] = $client->id;

        return DB::transaction(function () use ($client, $data) {
            $organization = Organization::create($data);

            $password = Str::random(12);

            $this->createInSham($organization, $client, $password);
            $res = true;
            if (!$res) $organization->delete();
            else {
                $daysInMonth = Carbon::now()->daysInMonth;

                $monthlyTariffPrice = $this->getTariffMonthlyPriceFromPriceList($client, Carbon::now());
                if ($monthlyTariffPrice <= 0 && $client->tariffPrice) {
                    $monthlyTariffPrice = (float) $client->tariffPrice->tariff_price;
                }

                $sum = $monthlyTariffPrice / $daysInMonth;

                if (!$client->is_demo) {
                    $currency = $client->currency;

                    $accountedAmount = $currency->symbol_code == 'USD' ? $sum / $currency->latestExchangeRate->kurs : $sum;

                    Transaction::create([
                        'client_id' => $client->id,
                        'organization_id' => $organization->id,
                        'tariff_id' => $client->tariff->id,
                        'sale_id' => $client->sale?->id,
                        'sum' => $sum,
                        'type' => 'Снятие',
                        'accounted_amount' => $accountedAmount

                    ]);
                    $sum = $monthlyTariffPrice;

                    $currency = $client->currency;

                    $accountedAmount = $currency->symbol_code == 'USD' ? $sum / $currency->latestExchangeRate->kurs : $sum;

                    Transaction::create([
                        'client_id' => $client->id,
                        'organization_id' => $organization->id,
                        'tariff_id' => $client->tariff->id,
                        'sale_id' => $client->sale?->id,
                        'sum' => $sum,
                        'type' => 'Снятие',
                        'accounted_amount' => $accountedAmount
                    ]);
                }
            }

            if (!$client->is_demo) {
                SendOrganizationLicense::dispatch($organization);
            }

            return $organization;
        });
    }

    public function update(Organization $organization, array $data)
    {
        $organization->update($data);
    }

    public function access(Organization $organization, array $data)
    {
        $client = $organization->client()->first();

        $reject_cause = $data['reject_cause'] ?? '';

        ActivationJob::dispatch(array($organization->id), $client->sub_domain, !$organization->has_access, false, auth()->id(), $reject_cause);
    }

    public function addPack(Organization $organization, array $data)
    {
        $organizationPack = OrganizationPack::create([
            'organization_id' => $organization->id,
            'pack_id' => $data['pack_id'],
            'date' => $data['date'],
            'amount' => $data['amount']
        ]);

        $res = $this->addPackInSham($organizationPack, $organization->client->sub_domain);

        if (!$res) $organizationPack->delete();

    }

    public function createInSham(Organization $organization, Client $client, string $password)
    {
        CreateOrganizationJob::dispatch($client, $organization, $password)->delay(120);
    }

    public function addPackInSham(OrganizationPack $organizationPack, string $sub_domain)
    {
        $domain = env('APP_DOMAIN');
        $url = 'https://' . $sub_domain . '-back.' . $domain . '/api/organization/add-pack';

        $organization = $organizationPack->organization()->first();
        $pack = $organizationPack->pack()->first();
        $data = [
            'type' => $pack->type,
            'b_organization_id' => $organization->id,
        ];

        if ($pack->type == 'user') {
            $data['user_count'] = $organizationPack->amount;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $data);

        return $response->successful();
    }

    public function addLegalInfo(Organization $organization, array $data)
    {
        $organization->update([
            'legal_name' => $data['legal_name'] ?? $organization->legal_name,
            'legal_address' => $data['legal_address'] ?? $organization->legal_address,
            'INN' => $data['INN'] ?? $organization->INN,
            'phone' => $data['phone'] ?? $organization->phone,
            'director' => $data['director'] ?? $organization->director,
        ]);
    }

    public function addOrganization(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        return Organization::create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'phone' => $data['phone']
        ]);
    }

}
