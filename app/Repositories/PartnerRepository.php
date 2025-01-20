<?php

namespace App\Repositories;

use App\Models\Partner;
use App\Repositories\Contracts\PartnerRepositoryInterface;

class PartnerRepository implements PartnerRepositoryInterface
{


    public function store(array $data)
    {
        Partner::create($data);
    }

    public function update(Partner $partner, array $data)
    {
        $partner->update($data);
    }

}
