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
        return PartnerRequest::where('partner_id', auth()->id())->paginate(20);
    }

    public function store(StoreRequest $request)
    {
        return $this->repository->store($request->validated());
    }

}
