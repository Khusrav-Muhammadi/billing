<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use App\Models\Organization;
use App\Models\User;

interface OrganizationRepositoryInterface
{
    public function store(Client $client, array $data);

    public function update(Organization $organization, array $data);

    public function access(Organization $organization, array $data);

    public function addPack(Organization $organization, array $data);

    public function addLegalInfo(Organization $organization, array $data);

    public function addOrganization(array $data);

}
