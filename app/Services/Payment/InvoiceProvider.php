<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\DTO\CreateInvoiceDTO;
use App\Services\Response\PaymentResponse;
use App\Services\Response\WebhookResponse;

class InvoiceProvider implements PaymentProviderInterface
{

    public function createInvoice(CreateInvoiceDTO $dto): PaymentResponse
    {
        // Генерация PDF
        $pdf = $this->generatePdf(
            $dto->amount,
            $dto->currency,
            $dto->metadata['client']
        );

        // Сохранение в storage
        $path = $this->saveInvoice($pdf);

        return new PaymentResponse(
            success: true,
            downloadUrl: Storage::url($path)
        );
    }

    public function handleWebhook(array $data): WebhookResponse
    {
        // Не требуется для PDF-счетов
    }
}
