<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Models\Partner;
use App\Models\PartnerStatus;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Http\Request;

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

    public function edit(Partner $partner)
    {
        $managers = User::all();
        $partnerStatuses = PartnerStatus::all();

        return view('admin.partners.edit', compact('partner', 'partnerStatuses'));
    }

    public function update(Partner $partner, UpdateRequest $request)
    {
        $this->repository->update($partner, $request->validated());

        return redirect()->route('partner.index');
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();

        return redirect()->back();
    }
}
