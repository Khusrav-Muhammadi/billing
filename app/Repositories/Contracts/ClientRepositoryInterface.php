<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use App\Models\User;

interface ClientRepositoryInterface
{
    public function index();

    public function store(array $data);

    public function update(Client $client, array $data);

    public function destroy(Client $client);

    public function createTransaction(Client $client, array $data);

    public function getBalance(array $data);

}
