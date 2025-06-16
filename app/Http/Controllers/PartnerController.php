<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\UpdateManagerRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Partner;
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
        $partnerStatuses = PartnerStatus::all();

        return view('admin.partners.create', compact('partnerStatuses'));
    }

    public function store(StoreRequest $request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('partner.index');
    }

    public function edit(User $partner)
    {
        $partnerStatuses = PartnerStatus::all();

        $managers = $this->repository->getManagers($partner->id);

        return view('admin.partners.edit', compact('partner', 'partnerStatuses', 'managers'));
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

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->back();
    }

    public function addManager(StoreRequest  $request)
    {
        $this->repository->storeManager($request->validated());

        return redirect()->route('partner.index');
    }
}
