<?php

namespace App\Repositories\Contracts;

use App\Models\Partner;
use App\Models\User;

interface PartnerRepositoryInterface
{
    public function index(array $data);

    public function store(array $data);

    public function update(User $partner, array $data);

}
