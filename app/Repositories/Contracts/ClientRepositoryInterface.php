<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use App\Models\Partner;
use App\Models\User;

interface ClientRepositoryInterface
{
    public function index(array $data);

    public function store(array $data);

    public function update(Client $client, array $data);

    public function activation(Client $client);

    public function createTransaction(Client $client, array $data);

    public function getBalance(array $data);

    public function getByPartner(array $data);

}
