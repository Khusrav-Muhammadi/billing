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
use App\Services\IntegrationActionLogService;
use App\Services\Organizations\OrganizationValidityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    private function getTariffMonthlyPriceFromPriceList(Client $client, Carbon $asOf): float
    {
        $currencyId = (int)($client->country?->currency_id ?? 0);
        if ($currencyId <= 0) return 0.0;

        // Client::tariff_id points to TariffCurrency in some flows, so prefer the base tariff id from tariffPrice.
        $baseTariffId = (int)($client->tariffPrice?->tariff_id ?? 0);
        if ($baseTariffId <= 0) {
            $baseTariffId = (int)($client->tariff_id ?? 0);
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

        return (float)($price?->sum ?? 0);
    }

    private function applyOrganizationSearch(Builder $query, ?string $search): void
    {
        $search = trim((string)$search);
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
                    $builder->orWhere('order_number', 'like', '%' . Organization::formatOrderNumber((int)$digits) . '%');
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
            $status = (bool)$data['status'];
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

    private function sortDirection(array $data): string
    {
        return strtolower((string)($data['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    }

    private function applyOrganizationSorting(Builder $query, array $data, bool $isDemo = false): void
    {
        $sort = (string)($data['sort'] ?? '');
        $direction = $this->sortDirection($data);

        match ($sort) {
            'order_number' => $query->orderBy('organizations.order_number', $direction),
            'name' => $query->orderBy('organizations.name', $direction),
            'phone' => $query->orderBy('organizations.phone', $direction),
            'created_at' => $query->orderBy('organizations.created_at', $direction),
            'email' => $query->orderBy($this->clientColumnSubquery('clients.email'), $direction),
            'country' => $query->orderBy($this->clientColumnSubquery('countries.name', true), $direction),
            'tariff' => $query->orderBy($this->tariffNameSubquery(), $direction),
            'users_count' => $query->orderBy($this->usersCountSubquery(), $direction),
            'balance' => $query->orderBy($this->balanceSubquery(), $direction),
            'valid_until' => $isDemo
                ? $query->orderBy($this->clientCreatedAtSubquery(), $direction)
                : $this->applyCalculatedValiditySorting($query, $direction),
            'last_activity' => $query->orderBy($this->clientColumnSubquery('clients.last_activity'), $direction),
            'status' => $isDemo
                ? $query->orderBy($this->demoStatusSubquery(), $direction)
                : $query->orderBy($this->connectedStatusSubquery(), $direction),
            'partner' => $query->orderBy($this->clientColumnSubquery('partners.name', true, true), $direction),
            'sub_domain' => $query->orderBy($this->clientColumnSubquery('clients.sub_domain'), $direction),
            default => $query->orderBy('organizations.id'),
        };

        if ($sort !== '' && $sort !== 'valid_until') {
            $query->orderBy('organizations.id');
        }
    }

    private function clientColumnSubquery(string $column, bool $joinCountry = false, bool $joinPartner = false)
    {
        $query = DB::table('clients')
            ->whereColumn('clients.id', 'organizations.client_id')
            ->limit(1);

        if ($joinCountry) {
            $query->leftJoin('countries', 'countries.id', '=', 'clients.country_id');
        }

        if ($joinPartner) {
            $query->leftJoin('users as partners', 'partners.id', '=', 'clients.partner_id');
        }

        return $query->select($column);
    }

    private function clientCreatedAtSubquery()
    {
        return $this->clientColumnSubquery('clients.created_at');
    }

    private function tariffNameSubquery()
    {
        return DB::table('connected_client_services')
            ->join('tariffs', 'tariffs.id', '=', 'connected_client_services.tariff_id')
            ->whereColumn('connected_client_services.client_id', 'organizations.id')
            ->where('tariffs.is_tariff', true)
            ->orderBy('connected_client_services.id')
            ->limit(1)
            ->select('tariffs.name');
    }

    private function usersCountSubquery()
    {
        return DB::table('connected_client_services')
            ->join('tariffs', 'tariffs.id', '=', 'connected_client_services.tariff_id')
            ->whereColumn('connected_client_services.client_id', 'organizations.id')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN tariffs.is_tariff = 1 THEN tariffs.user_count
                        WHEN tariffs.is_extra_user = 1 THEN COALESCE(connected_client_services.quantity, 1) * COALESCE(tariffs.user_count, 1)
                        ELSE 0
                    END
                ), 0)
            ");
    }

    private function balanceSubquery()
    {
        $clientCurrencySubquery = "
            SELECT countries.currency_id
            FROM clients
            LEFT JOIN countries ON countries.id = clients.country_id
            WHERE clients.id = organizations.client_id
            LIMIT 1
        ";

        return DB::table('client_balances')
            ->whereColumn('client_balances.organization_id', 'organizations.id')
            ->whereRaw("
                (
                    client_balances.currency_id = ($clientCurrencySubquery)
                    OR NOT EXISTS (
                        SELECT 1
                        FROM client_balances AS same_currency_balances
                        WHERE same_currency_balances.organization_id = organizations.id
                          AND same_currency_balances.currency_id = ($clientCurrencySubquery)
                    )
                )
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN client_balances.type = 'income' THEN client_balances.sum
                        WHEN client_balances.type = 'outcome' THEN -client_balances.sum
                        ELSE 0
                    END
                ), 0)
            ");
    }

    private function connectedStatusSubquery()
    {
        return DB::table('organization_connection_statuses')
            ->whereColumn('organization_connection_statuses.organization_id', 'organizations.id')
            ->orderByDesc('organization_connection_statuses.status_date')
            ->orderByDesc('organization_connection_statuses.updated_at')
            ->limit(1)
            ->selectRaw("CASE WHEN organization_connection_statuses.status = 'connected' THEN 1 ELSE 0 END");
    }

    private function demoStatusSubquery()
    {
        $cutoff = now()->subDays(14)->format('Y-m-d H:i:s');

        return DB::table('clients')
            ->whereColumn('clients.id', 'organizations.client_id')
            ->limit(1)
            ->selectRaw("CASE WHEN clients.created_at > '{$cutoff}' THEN 1 ELSE 0 END");
    }

    private function applyCalculatedValiditySorting(Builder $query, string $direction): void
    {
        $validityService = app(OrganizationValidityService::class);

        $ids = (clone $query)
            ->with(['client.country'])
            ->get()
            ->map(function (Organization $organization) use ($validityService): array {
                $validUntil = $validityService->calculateValidUntil($organization);

                return [
                    'id' => (int)$organization->id,
                    'timestamp' => $validUntil?->timestamp,
                ];
            })
            ->sort(function (array $left, array $right) use ($direction): int {
                if ($left['timestamp'] === null && $right['timestamp'] === null) {
                    return $left['id'] <=> $right['id'];
                }

                if ($left['timestamp'] === null) {
                    return 1;
                }

                if ($right['timestamp'] === null) {
                    return -1;
                }

                $leftValue = $left['timestamp'];
                $rightValue = $right['timestamp'];

                if ($leftValue === $rightValue) {
                    return $left['id'] <=> $right['id'];
                }

                return $direction === 'desc'
                    ? $rightValue <=> $leftValue
                    : $leftValue <=> $rightValue;
            })
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            $query->orderBy('organizations.id');
            return;
        }

        $query->orderByRaw('FIELD(organizations.id, ' . implode(',', $ids) . ')');
    }

    public function index(array $data)
    {
        $user = auth()->user();
        $query = Organization::query()
            ->when($user->role != 'admin', function ($q) use ($user) {
                $q->whereHas('client', function (Builder $q) use ($user) {
                    return $q->where('partner_id', $user->id);
                });
            })
            ->whereHas('connections')
            ->with(['client.partner', 'client.country.currency', 'connectedServices.tariff', 'latestConnection']);

        $this->applyOrganizationSearch($query, $data['search'] ?? null);
        $this->applyOrganizationFilters($query, $data);
        $this->applyValidityUntilFilter($query, $data['valid_until_to'] ?? null);

        $this->applyOrganizationSorting($query, $data);

        return $query->paginate(50)->withQueryString();
    }

    private function applyValidityUntilFilter(Builder $query, ?string $validUntilTo): void
    {
        $validUntilTo = trim((string)$validUntilTo);
        if ($validUntilTo === '') {
            return;
        }

        $targetDate = Carbon::parse($validUntilTo)->endOfDay();
        $validityService = app(OrganizationValidityService::class);

        $ids = (clone $query)
            ->with(['client.country'])
            ->get()
            ->filter(function (Organization $organization) use ($validityService, $targetDate): bool {
                $validUntil = $validityService->calculateValidUntil($organization);

                return $validUntil !== null && $validUntil->lessThanOrEqualTo($targetDate);
            })
            ->pluck('id')
            ->map(fn ($id) => (int)$id)
            ->all();

        if (empty($ids)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('id', $ids);
    }

    public function active(array $data)
    {
        $user = auth()->user();
        $query = Organization::query()
            ->when($user->role != 'admin', function ($q) use ($user) {
                $q->whereHas('client', function (Builder $q) use ($user) {
                    return $q->where('partner_id', $user->id);
                });
            })->whereHas('latestConnection', function ($q) {
                    $q->where('status', 'connected');
                })
            ->with(['client.partner', 'client.country.currency', 'connectedServices.tariff', 'latestConnection']);

        $this->applyOrganizationSearch($query, $data['search'] ?? null);
        $this->applyOrganizationFilters($query, $data);

        return $query->orderBy('id')->paginate(50);
    }

    public function inActive(array $data)
    {
        $user = auth()->user();
        $query = Organization::query()
            ->when($user->role != 'admin', function ($q) use ($user) {
                $q->whereHas('client', function (Builder $q) use ($user) {
                    return $q->where('partner_id', $user->id);
                });
            })
            ->whereHas('latestConnection', function ($q) {
                $q->where('status', 'disconnected');
            })
            ->with(['client.partner', 'client.country.currency', 'connectedServices.tariff', 'latestConnection']);

        $this->applyOrganizationSearch($query, $data['search'] ?? null);
        $this->applyOrganizationFilters($query, $data);

        return $query->orderBy('id')->paginate(50);
    }

    public function nfr(array $data)
    {
        $query = Organization::query()->whereHas('client', function (Builder $q)  {
                    return $q->where('nfr', true);
                })
            ->with(['client.partner', 'client.country.currency', 'connectedServices.tariff', 'latestConnection']);

        $this->applyOrganizationSearch($query, $data['search'] ?? null);
        $this->applyOrganizationFilters($query, $data);

        return $query->orderBy('id')->paginate(50);
    }

    public function demo(array $data)
    {
        $query = Organization::query()
            ->whereDoesntHave('connections')
            ->with(['client.partner', 'client.country.currency', 'connectedServices.tariff', 'latestConnection'])
            ->whereHas('client', function (Builder $clientQuery) {
                $clientQuery->where('nfr', false);
            });

        $this->applyOrganizationSearch($query, $data['search'] ?? null);

        if (!empty($data['country'])) {
            $countryId = (int)$data['country'];
            $query->whereHas('client', function (Builder $q) use ($countryId) {
                $q->where('country_id', $countryId);
            });
        }

        if (!empty($data['partner'])) {
            $partnerId = (int)$data['partner'];
            $query->whereHas('client', function (Builder $q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            });
        }

        if (isset($data['status']) && $data['status'] !== '') {
            $isActiveDemo = (bool)$data['status'];
            $cutoff = Carbon::now()->subDays(14);

            $query->whereHas('client', function (Builder $clientQuery) use ($isActiveDemo, $cutoff) {
                if ($isActiveDemo) {
                    $clientQuery->where('created_at', '>', $cutoff);
                    return;
                }

                $clientQuery->where('created_at', '<=', $cutoff);
            });
        }

        if (!empty($data['date_from'])) {
            $createdFrom = Carbon::parse($data['date_from'])->subWeeks(2)->startOfDay();
            $query->whereHas('client', function (Builder $clientQuery) use ($createdFrom) {
                $clientQuery->where('created_at', '>=', $createdFrom);
            });
        }

        if (!empty($data['date_to'])) {
            $createdTo = Carbon::parse($data['date_to'])->subWeeks(2)->endOfDay();
            $query->whereHas('client', function (Builder $clientQuery) use ($createdTo) {
                $clientQuery->where('created_at', '<=', $createdTo);
            });
        }

        $this->applyOrganizationSorting($query, $data, true);

        return $query->paginate(50)->withQueryString();
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
                    $monthlyTariffPrice = (float)$client->tariffPrice->tariff_price;
                }

                $sum = $monthlyTariffPrice / $daysInMonth;

                if (!$client->is_demo) {
                    $client->loadMissing(['country.currency.latestExchangeRate', 'tariff', 'tariffPrice', 'sale']);
                    $currencyCode = (string)($client->country?->currency?->symbol_code ?: 'USD');
                    $exchangeRate = (float)($client->country?->currency?->latestExchangeRate?->kurs ?? 1);
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
        $domain = config('services.sham.domain');
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

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($url, $data);
        } catch (\Throwable $e) {
            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: (int)$organization->id,
                clientId: (int)($organization->client_id ?? 0),
                action: 'add_pack',
                method: 'POST',
                url: $url,
                payload: $data,
                error: $e->getMessage()
            );

            return false;
        }

        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: (int)$organization->id,
            clientId: (int)($organization->client_id ?? 0),
            action: 'add_pack',
            method: 'POST',
            url: $url,
            payload: $data,
            response: $response
        );

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
