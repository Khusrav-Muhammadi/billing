<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreProcentRequest;
use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\StoreStatusRequest;
use App\Http\Requests\Partner\UpdateManagerRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Account;
use App\Models\Partner;
use App\Models\PartnerProcent;
use App\Models\PartnerStatus;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PartnerController extends Controller
{
    public function __construct(public PartnerRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        $partners = $this->repository->index($request->all());

        return view('admin.partners.index', compact('partners'));
    }

    public function create()
    {
        $accounts = Account::query()
            ->with('currency:id,symbol_code,name')
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);

        return view('admin.partners.create', compact('accounts'));
    }

    public function store(StoreRequest $request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('partner.index');
    }

    public function edit(User $partner)
    {
        $partnerStatuses = PartnerStatus::all();
        $accounts = Account::query()
            ->with('currency:id,symbol_code,name')
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);

        $managers = $this->repository->getManagers($partner->id);
        $procents = $this->repository->getProcent($partner->id);
        $statusHistory = $this->repository->getStatusHistory($partner->id);
        $partnerHistory = $partner->history()
            ->with(['changes', 'user'])
            ->orderByDesc('id')
            ->get();

        return view('admin.partners.edit', compact('partner', 'partnerStatuses', 'accounts', 'managers', 'procents', 'statusHistory', 'partnerHistory'));
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
