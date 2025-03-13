<?php

namespace App\Repositories;

use App\Models\Partner;
use App\Models\User;
use App\Repositories\Contracts\PartnerRepositoryInterface;

class PartnerRepository implements PartnerRepositoryInterface
{
    public function index(array $data)
    {
        $query = User::query()->where('role', 'partner');

        return $query->get();
    }

    public function store(array $data)
    {
        User::create($data);
    }

    public function update(User $partner, array $data)
    {
        $partner->update($data);
    }

}
