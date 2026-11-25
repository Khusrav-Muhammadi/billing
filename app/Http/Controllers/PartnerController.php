<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreProcentRequest;
use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\StoreStatusRequest;
use App\Http\Requests\Partner\UpdateManagerRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Account;
use App\Models\ClientHealthSnapshot;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\PartnerProcent;
use App\Models\PartnerStatus;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Services\ClientHealth\ClientHealthReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PartnerController extends Controller
{
    public function __construct(public PartnerRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        $read = app(ClientHealthReadService::class);
        $healthByPartner = $read->countsByPartner('paid');
        $activeClients = $read->activeClientsByPartner();
        $partners = $this->repository->index($request->all());

        if ($request->query('sort') === 'health') {
            $partners = $partners->sort(function ($a, $b) use ($healthByPartner) {
                $ar = $healthByPartner[$a->id]['red'] ?? 0;
                $br = $healthByPartner[$b->id]['red'] ?? 0;
                if ($ar !== $br) {
                    return $br <=> $ar;
                }
                $ay = $healthByPartner[$a->id]['yellow'] ?? 0;
                $by = $healthByPartner[$b->id]['yellow'] ?? 0;

                return $by <=> $ay;
            })->values();
        }

        $partnerHealthTotals = array_fill_keys(ClientHealthSnapshot::COLOR_ORDER, 0);
        foreach ($healthByPartner as $row) {
            foreach ($partnerHealthTotals as $color => $n) {
                $partnerHealthTotals[$color] += $row[$color] ?? 0;
            }
        }

        return view('admin.partners.index', compact('partners', 'healthByPartner', 'activeClients', 'partnerHealthTotals'));
    }

    public function create()
    {
        $accounts = Account::query()
            ->with('currency:id,symbol_code,name')
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);
        $countries = Country::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $partnerCurrencies = Currency::query()
            ->select('id', 'name', 'symbol_code')
            ->get()
            ->filter(fn ($currency) => in_array(strtoupper((string) $currency->symbol_code), ['USD', 'UZS'], true))
            ->sortBy(fn ($currency) => strtoupper((string) $currency->symbol_code) === 'USD' ? 1 : 2)
            ->values();

        return view('admin.partners.create', compact('accounts', 'partnerCurrencies', 'countries'));
    }

    public function store(StoreRequest $request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('partner.index');
    }

    public function edit(User $partner)
    {
        $accounts = Account::query()
            ->with('currency:id,symbol_code,name')
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);
        $countries = Country::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $partnerCurrencies = Currency::query()
            ->select('id', 'name', 'symbol_code')
            ->get()
            ->filter(fn ($currency) => in_array(strtoupper((string) $currency->symbol_code), ['USD', 'UZS'], true))
            ->sortBy(fn ($currency) => strtoupper((string) $currency->symbol_code) === 'USD' ? 1 : 2)
            ->values();

        $managers = $this->repository->getManagers($partner->id);
        $curators = $this->repository->getCurators($partner->id);
        $availableCurators = User::query()
            ->where('role', 'manager')
            ->whereNotIn('id', $curators->pluck('id')->all())
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);
        $procents = $this->repository->getProcent($partner->id);
        $statusHistory = $this->repository->getStatusHistory($partner->id);
        $partnerHistory = $partner->history()
            ->with(['changes', 'user'])
            ->orderByDesc('id')
            ->get();

        $read = app(ClientHealthReadService::class);
        $partnerHealthCounts = $read->counts((int) $partner->id, 'paid');
        $partnerHealthRows = collect($read->list([
            'partner_id' => (int) $partner->id,
            'type' => 'paid',
        ])->items())->take(10);
        $partnerHealthOrgs = $partnerHealthRows->isEmpty()
            ? collect()
            : Organization::query()->whereIn('client_id', $partnerHealthRows->pluck('client_id'))->pluck('id', 'client_id');

        return view('admin.partners.edit', compact('partner', 'accounts', 'partnerCurrencies', 'countries', 'managers', 'curators', 'availableCurators', 'procents', 'statusHistory', 'partnerHistory', 'partnerHealthCounts', 'partnerHealthRows', 'partnerHealthOrgs'));
    }

    public function update(User $partner, UpdateRequest $request)
    {
        $this->repository->update($partner, $request->validated());

        return redirect()->route('partner.index');
    }


    public function updateManager(User $user, UpdateManagerRequest $request)
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $this->repository->updateManager($user, $data);

        return redirect()->back();
    }

    public function destroy(User $partner)
    {
        $partner->delete();

        return redirect()->back();
    }

    public function destroyProcent(PartnerProcent $procent)
    {
        $procent->delete();

        return redirect()->back();
    }

    public function addManager(UpdateManagerRequest $request)
    {
        $this->repository->storeManager($request->validated());

        return redirect()->route('partner.index');
    }

    public function addProcent(User $user, StoreProcentRequest $request)
    {
        $this->repository->storeProcent($user, $request->validated());

        return redirect()->back();
    }

    public function addCurator(User $partner, Request $request)
    {
        $validated = $request->validate([
            'curator_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $curatorId = (int) $validated['curator_id'];
        $curator = User::query()
            ->where('id', $curatorId)
            ->where('role', 'manager')
            ->first();

        if (!$curator) {
            abort(404);
        }

        $this->repository->attachCurator((int) $partner->id, $curatorId);

        return redirect()->back();
    }

    public function removeCurator(User $partner, User $curator)
    {
        $this->repository->detachCurator((int) $partner->id, (int) $curator->id);

        return redirect()->back();
    }

    public function addStatus(User $user, StoreStatusRequest $request)
    {
        $this->repository->storeStatus($user, $request->validated());

        return redirect()->back();
    }

    public function editProcent(PartnerProcent $procent, StoreProcentRequest $request)
    {
        $this->repository->editProcent($procent, $request->validated());

        return redirect()->back();
    }
}
