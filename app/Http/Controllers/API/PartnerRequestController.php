<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerRequest\StoreRequest;
use App\Repositories\Contracts\PartnerRequestRepositoryInterface;

class PartnerRequestController extends Controller
{

    public function __construct(public PartnerRequestRepositoryInterface $repository) { }

    public function store(StoreRequest $request)
    {
        return $this->repository->store($request->validated());
    }

}
