<?php

namespace App\Services;

use App\Models\CommercialOffer;
use App\Models\Payment;
use App\Services\Mailing\ResendMailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClientPaymentInvoiceEmailService
{
    public function __construct(
        private ResendMailService $mailService
    ) {
    }

    public function send(Payment $payment): array
    {
        $payment->loadMissing('paymentItems');

        $offer = CommercialOffer::query()
            ->with([
                'organization:id,name,legal_name,INN,email,phone,order_number,client_id',
                'organization.client:id,name,email',
                'partner:id,name,email',
            ])
            ->where('payment_id', $payment->id)
            ->first();

        $recipient = $this->recipient($payment, $offer);
        if ($recipient['email'] === null) {
            throw new RuntimeException('Не найдена почта для отправки счета.');
        }

        $pdfContent = $this->pdfContent($payment, $offer);
        $fileName = 'invoice_' . $payment->id . '.pdf';

        $sent = $this->mailService->sendWithView(
            to: $recipient['email'],
            subject: 'Счет на оплату SHAMCRM № ' . $payment->id,
            view: 'mail.client_payment_invoice',
            data: [
                'payment' => $payment,
                'offer' => $offer,
                'recipientName' => $this->recipientName($payment, $offer),
                'log' => [
                    'payment_id' => $payment->id,
                    'recipient_source' => $recipient['source'],
                    'recipient_email' => $recipient['email'],
                    'partner_id' => $offer?->partner_id,
                    'file_name' => $fileName,
                    'attachment_size' => strlen($pdfContent),
                ],
            ],
            sendInternalCopy: false,
            logContext: [
                'organization_id' => $offer?->organization_id,
                'client_id' => $offer?->organization?->client_id,
                'commercial_offer_id' => $offer?->id,
                'action' => 'client_payment_invoice_email',
            ],
            attachments: [
                [
                    'filename' => $fileName,
                    'content' => $pdfContent,
                ],
            ]
        );

        if (!$sent) {
            Log::error('ClientPaymentInvoiceEmailService: failed to send invoice email', [
                'payment_id' => $payment->id,
                'recipient' => $recipient['email'],
            ]);

            throw new RuntimeException('Не удалось отправить счет на почту.');
        }

        return [
            'email' => $recipient['email'],
            'file_name' => $fileName,
        ];
    }

    private function pdfContent(Payment $payment, ?CommercialOffer $offer): string
    {
        return Pdf::loadView('payments-invoice', [
            'payment' => $payment,
            'offer' => $offer,
            'organizationOrderNumber' => (string) ($offer?->organization?->order_number ?? ''),
            'hideInvoiceActions' => true,
            'signatureUrl' => $this->imageDataUri(public_path('assets/images/invoice/imzo.png')),
            'stampUrl' => $this->imageDataUri(public_path('assets/images/invoice/pechat.png')),
        ])->setPaper('a4')->output();
    }

    private function recipient(Payment $payment, ?CommercialOffer $offer): array
    {
        $partnerId = (int) ($offer?->partner_id ?? 0);
        if ($partnerId > 0 && $partnerId !== 11) {
            $partnerEmail = trim((string) ($offer?->partner?->email ?? ''));
            if ($partnerEmail !== '') {
                return [
                    'email' => $partnerEmail,
                    'source' => 'partner',
                ];
            }
        }

        foreach ([
            'offer_client_email' => $offer?->client_email,
            'organization_client_email' => $offer?->organization?->client?->email,
            'organization_email' => $offer?->organization?->email,
            'payment_email' => $payment->email,
        ] as $source => $email) {
            $email = trim((string) $email);
            if ($email !== '') {
                return [
                    'email' => $email,
                    'source' => $partnerId === 11 ? 'client_partner_11_' . $source : 'client_' . $source,
                ];
            }
        }

        return [
            'email' => null,
            'source' => 'not_found',
        ];
    }

    private function recipientName(Payment $payment, ?CommercialOffer $offer): string
    {
        $name = trim((string) (
            $offer?->organization?->name
            ?: $offer?->client_name
            ?: $offer?->organization?->client?->name
            ?: $payment->name
        ));

        return $name !== '' ? $name : 'клиент';
    }

    private function imageDataUri(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
    }
}
