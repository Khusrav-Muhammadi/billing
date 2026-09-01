<?php

namespace App\Services\Mailing;

use App\Services\IntegrationActionLogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        $textView = $view . '_text';
        $text = view()->exists($textView)
            ? view($textView, $data)->render()
            : $this->htmlToText($html);

        $sent = $this->send($to, $subject, $html, $attachments, $text);

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

    public function send(string $to, string $subject, string $html, array $attachments = [], ?string $text = null): bool
    {
        $payload = $this->mailPayload($to, $subject, $html, $text ?? $this->htmlToText($html), $this->attachmentPayload($attachments));

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.resend.com/emails', $payload);

            if (!$response->failed()) {
                return true;
            }

            Log::error('ResendMailService: failed to send email', [
                'to'     => $to,
                'status' => $response->status(),
                'error'  => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ResendMailService: exception sending email', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('ResendMailService: Mail fallback failed', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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

    public function htmlToText(string $html): string
    {
        return MailText::fromHtml($html);
    }
}
