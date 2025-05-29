<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentProviderInterface;

class OctoBankProvider implements PaymentProviderInterface
{

    public function handleWebhook(array $data)
    {

    }

    public function createInvoice(array $data): bool
    {

    }
}
