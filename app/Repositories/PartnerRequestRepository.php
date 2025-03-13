<?php

namespace App\Repositories;

use App\Jobs\ChangeRequestStatusJob;
use App\Jobs\NewRequestJob;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Repositories\Contracts\PartnerRequestRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class PartnerRequestRepository implements PartnerRequestRepositoryInterface
{
    public function store(array $data)
    {
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        else $data['is_demo'] = false;

        $data['request_status'] = 'Новый';
        $data['partner_id'] = auth()->id();

        PartnerRequest::create($data);

        $user = User::first();

        NewRequestJob::dispatch($user, auth()->user()->name);
    }

    public function update(PartnerRequest $partnerRequest, array $data)
    {
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        else $data['is_demo'] = $partnerRequest->is_demo;


        $data['request_status'] = 'Обновлено';
        $partnerRequest->update($data);

        ChangeRequestStatusJob::dispatch(Auth::user(), $partnerRequest);
    }

    public function changeStatus(PartnerRequest $partnerRequest)
    {
        $partnerRequest->update(['request_status' => 'Новый']);
    }
}
