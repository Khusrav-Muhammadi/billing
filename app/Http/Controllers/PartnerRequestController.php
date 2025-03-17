<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Http\Requests\Partner\UpdateRequest;
use App\Http\Requests\PartnerRequest\RejectRequest;
use App\Http\Requests\PartnerRequest\UpdateReuqest;
use App\Jobs\ChangeRequestStatusJob;
use App\Models\Partner;
use App\Models\PartnerRequest;
use App\Models\PartnerStatus;
use App\Models\Sale;
use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PartnerRequestController extends Controller
{
    public function index()
    {
        $partnerRequests = PartnerRequest::all();

        return view('admin.partner_requests.index', compact('partnerRequests'));
    }

    public function reject(PartnerRequest $partnerRequest, RejectRequest $request)
    {
        $data = $request->validated();

        $partnerRequest->update([
            'reject_cause' => $data['reject_cause'],
            'request_status' => 'Отклонён'
        ]);

        $partner = $partnerRequest->partner()->first();

        ChangeRequestStatusJob::dispatch($partner, $partnerRequest, Auth::user());

        return redirect()->back()->with('success', 'Успешно изменено');
    }

    public function approve(PartnerRequest $partnerRequest)
    {
        $sales = Sale::all();
        $tariffs = Tariff::all();
        $partners = User::query()->where('role', 'partner')->get();

        return view('admin.clients.create', compact( 'tariffs', 'sales', 'partners', 'partnerRequest'));
    }

    public function edit(PartnerRequest $partnerRequest)
    {
        return view('admin.partner_requests.edit', compact('partnerRequest'));
    }

    public function update(PartnerRequest $partnerRequest, UpdateReuqest $request)
    {
        $partnerRequest->update($request->validated());

        $partnerRequests = PartnerRequest::all();
        return view('admin.partner_requests.index', compact('partnerRequest', 'partnerRequests'));
    }

}
