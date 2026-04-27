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
        $currencyId = (int) ($client->country?->currency_id ?? 0);
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

    private function applyOrganizationFilters(Builder $query, array $data): void
    {
        if (isset($data['clientType']) && $data['clientType'] !== '') {
            $type = $data['clientType'];
            $query->whereHas('client', function (Builder $q) use ($type) {
                if ($type === 'Клиенты') {
                    $q->where('nfr', false);
                } elseif ($type === 'Партнеры') {
                    $q->where('nfr', true);
                }
            });
        }

        if (isset($data['status']) && $data['status'] !== '') {
            $status = (bool) $data['status'];
            if ($status) {
                $query->whereHas('latestConnection', function (Builder $q) {
                    $q->where('status', 'connected');
                });
            } else {
                $query->where(function (Builder $q) {
                    $q->doesntHave('latestConnection')
                      ->orWhereHas('latestConnection', function (Builder $q2) {
                          $q2->where('status', '!=', 'connected');
                      });
                });
            }
        }

        if (!empty($data['tariff'])) {
            $tariffId = $data['tariff'];
            $query->whereHas('connectedServices', function (Builder $q) use ($tariffId) {
                $q->where('tariff_id', $tariffId);
            });
        }

        if (!empty($data['country'])) {
            $countryId = $data['country'];
            $query->whereHas('client', function (Builder $q) use ($countryId) {
                $q->where('country_id', $countryId);
            });
        }

        if (!empty($data['partner'])) {
            $partnerId = $data['partner'];
            $query->whereHas('client', function (Builder $q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            });
        }
    }

    public function index(array $data)
    {
        $query = Organization::query()
            ->whereHas('connections')
            ->with(['client.partner', 'client.country.currency', 'connectedServices.tariff', 'latestConnection']);

        $this->applyOrganizationSearch($query, $data['search'] ?? null);
        $this->applyOrganizationFilters($query, $data);

        return $query->orderBy('id')->get();
    }

    public function demo(array $data)
    {
        $query = Organization::query()
            ->whereDoesntHave('connections')
            ->with(['client.partner', 'client.country.currency', 'connectedServices.tariff', 'latestConnection'])
            ->whereHas('client', function (Builder $clientQuery) {
                $clientQuery->where('is_demo', true)->where('nfr', false);
            });

        $this->applyOrganizationSearch($query, $data['search'] ?? null);
        $this->applyOrganizationFilters($query, $data);

        return $query->orderBy('id')->get();
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
                    $client->loadMissing(['country.currency.latestExchangeRate', 'tariff', 'tariffPrice', 'sale']);
                    $currencyCode = (string) ($client->country?->currency?->symbol_code ?: 'USD');
                    $exchangeRate = (float) ($client->country?->currency?->latestExchangeRate?->kurs ?? 1);
                    if ($exchangeRate <= 0) {
                        $exchangeRate = 1;
                    }

                    $accountedAmount = $currencyCode === 'USD' ? $sum : $sum / $exchangeRate;

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

                    $accountedAmount = $currencyCode === 'USD' ? $sum : $sum / $exchangeRate;

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
