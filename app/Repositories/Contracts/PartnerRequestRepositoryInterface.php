<?php

namespace App\Repositories\Contracts;

use App\Models\Partner;

interface PartnerRequestRepositoryInterface
{
    public function store(array $data);

}
