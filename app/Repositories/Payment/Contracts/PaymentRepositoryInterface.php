<?php

namespace App\Repositories\Payment\Contracts;

use Illuminate\Http\Request;

interface PaymentRepositoryInterface
{
    public function createInvoice(array $data);

    public function webhook(Request $request);
}
