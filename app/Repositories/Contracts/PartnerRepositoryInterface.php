<?php

namespace App\Repositories\Contracts;

use App\Models\Partner;

interface PartnerRepositoryInterface
{
    public function index(array $data);

    public function store(array $data);

    public function update(Partner $partner, array $data);

}
