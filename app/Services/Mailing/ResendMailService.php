<?php

namespace App\Services\Mailing;

use App\Services\IntegrationActionLogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResendMailService
{
    private string $apiKey;
    private string $fromEmail;

    public function __construct()
    {
        $this->apiKey    = config('services.resend.api-key');
        $this->fromEmail = config('services.resend.mail_from');
    }

    public function sendWithView(
        string $to,
        string $subject,
        string $view,
        array $data = [],
        bool $sendInternalCopy = true,
        array $logContext = [],
        array $attachments = []
    ): bool
    {
        $html = view($view, $data)->render();
        $text = strip_tags($html);



        $sent = $this->send($to, $subject, $html, $attachments);

        if (!empty($logContext)) {
            app(IntegrationActionLogService::class)->logEmail(
                organizationId: isset($logContext['organization_id']) ? (int)$logContext['organization_id'] : null,
                clientId: isset($logContext['client_id']) ? (int)$logContext['client_id'] : null,
                action: (string)($logContext['action'] ?? 'email'),
                recipient: $to,
                subject: $subject,
                payload: [
                    'view' => $view,
                    'data' => $data,
                    'request_body' => $this->mailPayload($to, $subject, $html, $text, $this->attachmentPayload($attachments, false)),
                    'email_body' => [
                        'html' => $html,
                        'text' => $text,
                    ],
                ],
                successful: $sent,
                commercialOfferId: isset($logContext['commercial_offer_id']) ? (int)$logContext['commercial_offer_id'] : null
            );
        }

        return $sent;
    }

    public function send(string $to, string $subject, string $html, array $attachments = []): bool
    {
        $payload = $this->mailPayload($to, $subject, $html, strip_tags($html), $this->attachmentPayload($attachments));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post('https://api.resend.com/emails', $payload);

        if ($response->failed()) {
            Log::error('ResendMailService: failed to send email', [
                'to'     => $to,
                'status' => $response->status(),
                'error'  => $response->json(),
            ]);
            return false;
        }

        return true;
    }

    private function mailPayload(string $to, string $subject, string $html, string $text, array $attachments = []): array
    {
        $payload = [
            'from' => "shamCRM <{$this->fromEmail}>",
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];

        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    private function attachmentPayload(array $attachments, bool $includeContent = true): array
    {
        return collect($attachments)
            ->map(function (array $attachment) use ($includeContent) {
                $payload = [
                    'filename' => (string) ($attachment['filename'] ?? 'attachment'),
                ];

                if ($includeContent) {
                    $payload['content'] = base64_encode((string) ($attachment['content'] ?? ''));
                }

                return $payload;
            })
            ->values()
            ->all();
    }
}
