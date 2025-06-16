<?php

namespace App\Repositories\Contracts;

use App\Models\Partner;
use App\Models\User;

interface PartnerRepositoryInterface
{
    public function index(array $data);

    public function store(array $data);
    public function storeManager(array $data);
    public function updateManager(User $user, array $data);
    public function getManagers(int $partner_id);

    public function update(User $partner, array $data);

}
