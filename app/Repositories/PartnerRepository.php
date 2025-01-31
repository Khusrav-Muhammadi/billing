<?php

namespace App\Repositories;

use App\Models\Partner;
use App\Repositories\Contracts\PartnerRepositoryInterface;

class PartnerRepository implements PartnerRepositoryInterface
{
    public function index(array $data)
    {
        $query = Partner::query();

        $query = $query->filter($data);

        return $query->with('history.changes', 'history.user')->get();
    }

    public function store(array $data)
    {
        Partner::create($data);
    }

    public function update(Partner $partner, array $data)
    {
        $partner->update($data);
    }

}
