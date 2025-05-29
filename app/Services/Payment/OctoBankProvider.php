<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;

class OctoBankProvider implements PaymentProviderInterface
{


    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse
    {
        // TODO: Implement createInvoice() method.
    }

    public function handleWebhook(array $data): WebhookResponse
    {
        // TODO: Implement handleWebhook() method.
    }
}
