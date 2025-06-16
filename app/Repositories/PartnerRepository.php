<?php

namespace App\Repositories;

use App\Jobs\SendPartnerDataJob;
use App\Models\Partner;
use App\Models\PartnerStatus;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class PartnerRepository implements PartnerRepositoryInterface
{
    public function index(array $data)
    {
        $query = User::query()->where('role', 'partner');

        return $query->get();
    }

    public function store(array $data)
    {
        $data['partner_status_id'] = PartnerStatus::first()->id;
        $data['login'] = $data['email'];
        $data['role'] = 'partner';
        $data['password'] = Hash::make($data['email']);

        User::create($data);

//        SendPartnerDataJob::dispatch($user, $password);
    }

    public function getManagers(int $partner_id)
    {
        return User::query()->where('role', 'manager')->where('partner_id', $partner_id)->get();
    }

    public function storeManager(array $data)
    {
        $password = $data['password'];

        $data['role'] = 'manager';
        $data['login'] = $data['email'];

        $user = User::create($data);
    }

    public function update(User $partner, array $data)
    {
        $partner->update($data);
    }

    public function updateManager(User $user, array $data)
    {
        $user->update($data);
    }
}
