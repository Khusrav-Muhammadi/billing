<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\RejectRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Models\Client;
use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\Country;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\OrganizationConnectionStatus;
use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrganizationV2Controller extends Controller
{
    public function __construct(public OrganizationRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        $organizations = $this->repository->index($request->all());
        $this->hydrateRealBalances($organizations);

        $partners = User::query()->where('role', 'partner')->get();
        $tariffs = Tariff::all();
        $countries = Country::all();

        if ($request->ajax()) {
            return view('admin.partials.organizations_v2', compact('organizations'))->render();
        }

        return view('v2.organizations_v2.index', compact('organizations', 'partners', 'tariffs', 'countries'));
    }

    public function demo(Request $request)
    {
        $organizations = $this->repository->demo($request->all());
        $this->hydrateRealBalances($organizations);

        $partners = User::query()->where('role', 'partner')->get();
        $tariffs = Tariff::all();
        $countries = Country::all();

        if ($request->ajax()) {
            return view('admin.partials.organizations_v2', compact('organizations'))->render();
        }

        return view('v2.organizations_v2.demo', compact('organizations', 'partners', 'tariffs', 'countries'));
    }

    public function store(Client $client, StoreRequest $request): RedirectResponse
    {
        $organization = $this->repository->store($client, $request->validated());

        if (!$organization) {
            return redirect()->back()->with('error', 'Не удалось создать организацию');
        }

        return redirect()->back();
    }

    public function show(Organization $organization)
    {
        $organization->load([
            'client:id,name,email,phone,sub_domain,last_activity,is_active,partner_id,tariff_id,country_id',
            'client.country:id,name,currency_id',
            'client.country.currency:id,name,symbol_code',
            'client.partner:id,name',
            'client.tariffPrice:id,tariff_id',
            'client.tariffPrice.tariff:id,name,user_count',
        ]);

        $connectedServices = ConnectedClientServices::query()
            ->where('client_id', (int) $organization->id)
            ->with([
                'tariff:id,name',
                'offerCurrency:id,name,symbol_code',
            ])
            ->orderBy('date')
            ->get();

        $connectionStatusHistory = collect();
        if (Schema::hasTable('organization_connection_statuses')) {
            $connectionStatusHistory = OrganizationConnectionStatus::query()
                ->where('organization_id', (int) $organization->id)
                ->with([
                    'author:id,name',
                    'commercialOffer:id,request_type',
                    'dayClosing:id,doc_number,date',
                ])
                ->orderByDesc('status_date')
                ->orderByDesc('id')
                ->get();
        }

        $balanceOperations = ClientBalance::query()
            ->where('organization_id', (int) $organization->id)
            ->with('currency:id,name,symbol_code')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $realBalance = $this->calculateRealBalance($organization, $balanceOperations);

        return view('v2.organizations_v2.show', compact(
            'organization',
            'connectedServices',
            'connectionStatusHistory',
            'balanceOperations',
            'realBalance'
        ));
    }

    public function update(Organization $organization, UpdateRequest $request): RedirectResponse
    {
        $this->repository->update($organization, $request->validated());

        return redirect()->back();
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->repository->destroy($organization);

        return redirect()->back();
    }

    public function access(Organization $organization, RejectRequest $request)
    {
        $this->repository->access($organization, $request->validated());

        return redirect()->back();
    }

    public function addPack(Organization $organization, AddPackRequest $request)
    {
        $this->repository->addPack($organization, $request->validated());

        return redirect()->back();
    }

    public function deletePack(OrganizationPack $organizationPack)
    {
        $organizationPack->delete();

        return redirect()->back();
    }

    private function hydrateRealBalances(Collection $organizations): void
    {
        if ($organizations->isEmpty()) {
            return;
        }

        $organizationIds = $organizations->pluck('id')->map(fn ($id) => (int) $id)->all();

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

        foreach ($organizations as $organization) {
            $rows = $balanceByOrganization->get((int) $organization->id, collect());
            $targetCurrencyId = (int) ($organization->client?->country?->currency_id ?? 0);

            if ($targetCurrencyId > 0) {
                $sameCurrencyRows = $rows->where('currency_id', $targetCurrencyId)->values();
                if ($sameCurrencyRows->isNotEmpty()) {
                    $rows = $sameCurrencyRows;
                }
            }

            $income = (float) $rows->sum('total_income');
            $outcome = (float) $rows->sum('total_outcome');

            $organization->setAttribute('real_balance', round($income - $outcome, 4));
        }
    }

    private function calculateRealBalance(Organization $organization, Collection $operations): float
    {
        $targetCurrencyId = (int) ($organization->client?->country?->currency_id ?? 0);

        $rows = $operations;
        if ($targetCurrencyId > 0) {
            $sameCurrencyRows = $operations->where('currency_id', $targetCurrencyId)->values();
            if ($sameCurrencyRows->isNotEmpty()) {
                $rows = $sameCurrencyRows;
            }
        }

        $income = (float) $rows->where('type', 'income')->sum('sum');
        $outcome = (float) $rows->where('type', 'outcome')->sum('sum');

        return round($income - $outcome, 4);
    }

}
