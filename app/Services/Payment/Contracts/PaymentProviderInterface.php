<?php

namespace App\Services\Payment\Contracts;

interface PaymentProviderInterface
{
    public function handleWebhook(array $data);

    public function createInvoice(array $data): bool;
}
