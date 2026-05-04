<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\LegalInfoRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use App\Models\OrganizationPack;
use App\Models\Pack;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Services\Organizations\OrganizationValidityService;
use App\Services\Sale\SaleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class OrganizationController extends Controller
{
    public function __construct(public OrganizationRepositoryInterface $repository)
    {
    }

    public function store(Client $client, StoreRequest $request): JsonResponse
    {
        $organization = $this->repository->store($client, $request->validated());

        if (!$organization) {
            return response()->json([
                'error' => 'Не удалось создать организацию, попробуйте позже'
            ], 400);
        }

        return response()->json(['success' => true]);
    }

    public function show(Organization $organization)
    {
        $packs = Pack::where('tariff_id', $organization->client?->tariff_id)->with('tariff')->get();

        return response()->json(['success' => true, 'packs' => $packs, 'organization' => $organization->load('packs.pack', 'businessType')]);
    }

    public function update(Organization $organization, \App\Http\Requests\Organization\UpdateRequest $request)
    {
        $this->repository->update($organization, $request->validated());

        return response()->json(['success' => true]);
    }

    public function destroy(Organization $organization)
    {
        $this->repository->destroy($organization);

        return response()->json(['success' => true]);
    }

    public function access(Organization $organization)
    {
        $this->repository->access($organization, []);

        return response()->json(['success' => true]);
    }

    public function addPack(int $id, AddPackRequest $request)
    {
        $organization = Organization::find($id);
        $this->repository->addPack($organization, $request->validated());

        return response()->json(['success' => true]);

    }

    public function deletePack(int $id)
    {
        OrganizationPack::where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function indexV2(Request $request): JsonResponse
    {
        $organizations = $this->repository->index($request->all());
        $this->hydrateRealBalances($organizations);
        app(OrganizationValidityService::class)->hydrate($organizations);
        return response()->json([
            'organizations' => $organizations,
        ]);
    }
    public function active(Request $request): JsonResponse
    {
        $organizations = $this->repository->active($request->all());
        $this->hydrateRealBalances($organizations);
        return response()->json([
            'organizations' => $organizations,
        ]);
    }

    public function inActive(Request $request): JsonResponse
    {
        $organizations = $this->repository->inActive($request->all());
        $this->hydrateRealBalances($organizations);
        return response()->json([
            'organizations' => $organizations,
        ]);
    }
    public function nfr(Request $request): JsonResponse
    {
        $organizations = $this->repository->nfr($request->all());
        $this->hydrateRealBalances($organizations);
        return response()->json([
            'organizations' => $organizations,
        ]);
    }
    public function demo(Request $request): JsonResponse
    {
        $authUser = auth()->user();

        $organizationsQuery = Organization::query()
            ->with([
                'client:id,name,email,phone,sub_domain,last_activity,is_active,is_demo,partner_id,tariff_id,country_id,manager_id,nfr,created_at',
                'client.country:id,name,currency_id',
                'client.country.currency:id,name,symbol_code',
                'client.partner:id,name',
                'client.tariffPrice:id,tariff_id',
                'client.tariffPrice.tariff:id,name,user_count',
            ])
            ->where(function ($query) use ($request) {
                $query->whereHas('balances')
                    ->orWhereHas('client', function (Builder $clientQuery) use ($request) {
                        $this->applyV2ClientFilters($clientQuery, $request);
                    });
            })
            ->whereDoesntHave('connections')
            ->whereHas('client', function (Builder $clientQuery) use ($authUser) {
                if ($authUser->id !== 11) {
                    $clientQuery->where('partner_id', Auth::id());
                }
                $clientQuery->where('nfr', false);
            });

        if ($request->filled('country')) {
            $countryId = (int)$request->query('country');
            $organizationsQuery->whereHas('client', function (Builder $clientQuery) use ($countryId): void {
                $clientQuery->where('country_id', $countryId);
            });
        }

        if ($request->filled('partner')) {
            $partnerId = (int)$request->query('partner');
            $organizationsQuery->whereHas('client', function (Builder $clientQuery) use ($partnerId): void {
                $clientQuery->where('partner_id', $partnerId);
            });
        }

        if ($request->query('status') !== null && $request->query('status') !== '') {
            $isActiveDemo = (bool)$request->query('status');
            $cutoff = now()->subDays(14);
            $organizationsQuery->whereHas('client', function (Builder $clientQuery) use ($isActiveDemo, $cutoff): void {
                if ($isActiveDemo) {
                    $clientQuery->where('created_at', '>', $cutoff);
                    return;
                }

                $clientQuery->where('created_at', '<=', $cutoff);
            });
        }

        if ($request->filled('date_from')) {
            $createdFrom = Carbon::parse((string)$request->query('date_from'))->subWeeks(2)->startOfDay();
            $organizationsQuery->whereHas('client', function (Builder $clientQuery) use ($createdFrom): void {
                $clientQuery->where('created_at', '>=', $createdFrom);
            });
        }

        if ($request->filled('date_to')) {
            $createdTo = Carbon::parse((string)$request->query('date_to'))->subWeeks(2)->endOfDay();
            $organizationsQuery->whereHas('client', function (Builder $clientQuery) use ($createdTo): void {
                $clientQuery->where('created_at', '<=', $createdTo);
            });
        }

        $search = trim((string)$request->query('search', ''));
        if ($search !== '') {
            $this->applyV2OrganizationSearch($organizationsQuery, $search);
        }

        $organizations = $organizationsQuery
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $this->hydrateRealBalances($organizations);
        $this->hydrateDemoValidateDates($organizations);

        return response()->json([
            'organizations' => $organizations,
        ]);
    }


    public function showV2(Organization $organization): JsonResponse
    {
        $organization->load([
            'client:id,name,email,phone,sub_domain,last_activity,is_active,partner_id,tariff_id,country_id,manager_id',
            'client.country:id,name,currency_id',
            'client.country.currency:id,name,symbol_code',
            'client.partner:id,name',
            'client.tariffPrice:id,tariff_id',
            'client.tariffPrice.tariff:id,name,user_count',
        ]);


        $connectedServices = ConnectedClientServices::query()
            ->where('client_id', (int)$organization->id)
            ->with([
                'tariff:id,name',
                'offerCurrency:id,name,symbol_code',
            ])
            ->orderBy('date')
            ->get();

        $connectionStatusHistory = OrganizationConnectionStatus::query()
            ->where('organization_id', (int)$organization->id)
            ->with([
                'author:id,name',
                'commercialOffer:id,request_type',
                'dayClosing:id,doc_number,date',
            ])
            ->orderByDesc('status_date')
            ->orderByDesc('id')
            ->get();

        $balanceOperations = ClientBalance::query()
            ->where('organization_id', (int)$organization->id)
            ->where('type', 'income')
            ->with('currency:id,name,symbol_code')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $realBalance = $this->calculateRealBalance($organization, $balanceOperations);

        return response()->json([
            'organization' => $organization,
            'connected_services' => $connectedServices,
            'connection_status_history' => $connectionStatusHistory,
            'balance_operations' => $balanceOperations,
            'real_balance' => $realBalance,
        ]);
    }

    public function tariffInfo(Organization $organization)
    {
        $transaction = Transaction::where('organization_id', $organization->id)->first();

        $client = $organization->client;

        $tariffPrice = $client->tariffPrice;

        $currentMonth = Carbon::now();

        $daysInMonth = $currentMonth->daysInMonth;

        $sum = $tariffPrice->tariff_price / $daysInMonth;

        $saleTariffPrice = 0;

        $saleService = new SaleService();
        $activeSales = $saleService->getActiveSales();
        foreach ($activeSales as $activeSale) {
            if ($activeSale->min_months != 12) {
                continue;
            }
            if ($activeSale->apply_to == 'progressive') {
                $saleTariffPrice = $tariffPrice->tariff_price * ($activeSale->amount / 100) * 12;
            }
        }

        $days = $client->is_demo ? 14 : ($organization->balance + $saleTariffPrice) / round($sum, 7);

        $startDate = $client->is_demo ? $client->created_at : $transaction->created_at;

        $endDate = Carbon::now()->addDays($days);

        return [
            'name' => $tariffPrice->tariff?->name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'users_count' => $tariffPrice->tariff->user_count,
            'price' => $tariffPrice->tariff_price,
            'balance' => $organization->balance,
//            'license_price' => $tariffPrice->license_price,
            'id' => $tariffPrice->id,
            'country' => $client->country,
            'is_demo' => $client->is_demo,
            'days_left' => (int)$days
        ];
    }

    public function getLegalInfo(Organization $organization)
    {
        return [
            'legal_name' => $organization->legal_name,
            'legal_legal_address' => $organization->legal_address,
            'legal_INN' => $organization->INN,
            'legal_phone' => $organization->phone,
            'legal_director' => $organization->director,
        ];
    }

    public function addLegalInfo(Organization $organization, LegalInfoRequest $request)
    {
        return $this->repository->addLegalInfo($organization, $request->validated());
    }

    public function addOrganization(StoreRequest $request)
    {
        $data = $request->validated();
        $data['sub_domain'] = parse_url($request->fullUrl(), PHP_URL_HOST);

        $organization = $this->repository->addOrganization($data);

        return [
            'organization' => $organization,
            'tariff_id' => $organization->client->tariff_id
        ];
    }

    private function applyV2ClientFilters(
        Builder $query,
        Request $request
    ): void
    {


        $clientType = trim((string)$request->query('clientType', ''));
        if ($clientType === 'Клиенты') {
            $query->where('nfr', false);
        } elseif ($clientType === 'Партнеры') {
            $query->where('nfr', true);
        }

        $statusRaw = $request->query('status');
        if ($statusRaw !== null && $statusRaw !== '') {
            $query->where('is_active', (bool)$statusRaw);
        }

        $tariffId = (int)$request->query('tariff', 0);
        if ($tariffId > 0) {
            $query->where('tariff_id', $tariffId);
        }
    }

    private function applyV2OrganizationSearch(Builder $query, string $search): void
    {
        $searchTerm = '%' . $search . '%';
        $digits = preg_replace('/\D+/', '', $search);
        $digits = is_string($digits) ? $digits : '';

        $query->where(function (Builder $builder) use ($searchTerm, $digits): void {
            $builder
                ->where('name', 'like', $searchTerm)
                ->orWhere('phone', 'like', $searchTerm)
                ->orWhere('email', 'like', $searchTerm)
                ->orWhereHas('client', function (Builder $clientQuery) use ($searchTerm): void {
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

    private function canAccessOrganization(Organization $organization): bool
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return false;
        }

        if ($authUser->role === 'partner') {
            return (int)($organization->client?->partner_id ?? 0) === (int)$authUser->id;
        }

        if ($authUser->role === 'manager') {
            return (int)($organization->client?->manager_id ?? 0) === (int)$authUser->id;
        }

        return true;
    }

    /**
     * Adds computed `real_balance` attribute for every organization item.
     *
     * Repository methods may return either Eloquent Collection (indexV2, demo)
     * or LengthAwarePaginator (active/inActive/nfr). Support both to keep APIs stable.
     */
    private function hydrateRealBalances(Collection|LengthAwarePaginator $organizations): void
    {
        $items = $organizations instanceof LengthAwarePaginator
            ? $organizations->getCollection()
            : $organizations;

        if ($items->isEmpty()) {
            return;
        }

        $organizationIds = $items->pluck('id')->map(fn($id) => (int)$id)->all();

        $balanceByOrganization = ClientBalance::query()
            ->selectRaw("
                organization_id,
                currency_id,
                COALESCE(SUM(CASE WHEN type = 'income' THEN sum ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN type = 'outcome' THEN sum ELSE 0 END), 0) AS total_outcome
            ")
            ->whereIn('organization_id', $organizationIds)
            ->groupBy('organization_id', 'currency_id')
            ->get()
            ->groupBy('organization_id');

        foreach ($items as $organization) {
            $rows = $balanceByOrganization->get((int)$organization->id, collect());
            $targetCurrencyId = (int)($organization->client?->country?->currency_id ?? 0);

            if ($targetCurrencyId > 0) {
                $sameCurrencyRows = $rows->where('currency_id', $targetCurrencyId)->values();
                if ($sameCurrencyRows->isNotEmpty()) {
                    $rows = $sameCurrencyRows;
                }
            }

            $income = (float)$rows->sum('total_income');
            $outcome = (float)$rows->sum('total_outcome');

            $organization->setAttribute('real_balance', round($income - $outcome, 4));
        }

        if ($organizations instanceof LengthAwarePaginator) {
            $organizations->setCollection($items);
        }
    }

    private function hydrateDemoValidateDates(Collection|LengthAwarePaginator $organizations): void
    {
        $items = $organizations instanceof LengthAwarePaginator
            ? $organizations->getCollection()
            : $organizations;

        foreach ($items as $organization) {
            if (!$organization->client || !$organization->client->is_demo) {
                continue;
            }

            $organization->client->setAttribute(
                'validate_date',
                optional($organization->client->created_at)->copy()?->addWeeks(2)
            );
        }

        if ($organizations instanceof LengthAwarePaginator) {
            $organizations->setCollection($items);
        }
    }

    private function calculateRealBalance(Organization $organization, Collection $operations): float
    {
        $targetCurrencyId = (int)($organization->client?->country?->currency_id ?? 0);

        $rows = $operations;
        if ($targetCurrencyId > 0) {
            $sameCurrencyRows = $operations->where('currency_id', $targetCurrencyId)->values();
            if ($sameCurrencyRows->isNotEmpty()) {
                $rows = $sameCurrencyRows;
            }
        }

        $income = (float)$rows->where('type', 'income')->sum('sum');
        $outcome = (float)$rows->where('type', 'outcome')->sum('sum');

        return round($income - $outcome, 4);
    }

}
