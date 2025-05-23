<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerRequest\StoreRequest;
use App\Models\PartnerRequest;
use App\Repositories\Contracts\PartnerRequestRepositoryInterface;

class PartnerRequestController extends Controller
{

    public function __construct(public PartnerRequestRepositoryInterface $repository) { }

    public function index()
    {
        return PartnerRequest::where('partner_id', auth()->id())->with('tariff')->paginate(20);
    }

    public function show(PartnerRequest $partnerRequest)
    {
        return $partnerRequest->load('tariff');
    }

    public function store(StoreRequest $request)
    {
        return $this->repository->store($request->validated());
    }

    public function update(PartnerRequest $partnerRequest, StoreRequest $request)
    {
        return $this->repository->update($partnerRequest, $request->validated());
    }

    public function changeStatus(PartnerRequest $partnerRequest)
    {
        return $this->repository->changeStatus($partnerRequest);
    }

}
