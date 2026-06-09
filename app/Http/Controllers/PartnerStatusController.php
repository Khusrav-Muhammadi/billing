<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Http\Requests\PartnerStatuses\PartnerStatusStoreRequest;
use App\Models\Partner;
use App\Models\PartnerStatus;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Http\Request;

class PartnerStatusController extends Controller
{
    public function __construct(public PartnerRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        return view('admin.partner-status.index');
    }

    public function store(PartnerStatusStoreRequest $request)
    {
        PartnerStatus::query()->create($request->validated());

        return redirect()->back()->with('success', 'Успешно ');
    }

    public function update(PartnerStatus $partnerStatus, PartnerStatusStoreRequest $request)
    {
        $partnerStatus->update($request->validated());

        return redirect()->back()->with('success', 'Успешно ');
    }

    public function destroy(PartnerStatus $partnerStatus)
    {
        $partner->delete();

        return redirect()->back();
    }
}
