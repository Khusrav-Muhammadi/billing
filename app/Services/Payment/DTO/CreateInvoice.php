<?php

namespace App\Services\Payment\DTO;

use App\Models\Client;

class CreateInvoice
{
    public function __construct(public int $organizationId, public int $tariffId) {

    }
}
