<?php

namespace App\Repositories\Contracts;

use App\Models\Partner;
use App\Models\PartnerRequest;

interface PartnerRequestRepositoryInterface
{
    public function store(array $data);

    public function update(PartnerRequest $partnerRequest, array $data);

}
