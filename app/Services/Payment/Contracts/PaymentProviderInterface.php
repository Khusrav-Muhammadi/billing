<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;

interface PaymentProviderInterface
{
    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse;

    public function handleWebhook(array $data) :WebhookResponse;
}
