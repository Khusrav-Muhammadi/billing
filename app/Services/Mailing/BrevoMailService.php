<?php

namespace App\Services\Mailing;

use App\Services\IntegrationActionLogService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class BrevoMailService
{
    public const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    private string $apiKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->apiKey = (string) config('services.brevo.api_key');
        $this->fromEmail = (string) config('services.brevo.mail_from');
        $this->fromName = (string) config('services.brevo.mail_from_name', 'shamCRM');
    }

    public function sendWithView(
        string $to,
        string $subject,
        string $view,
        array $data = [],
        bool $sendInternalCopy = true,
        array $logContext = [],
        array $attachments = []
    ): bool {
        $html = view($view, $data)->render();
        $text = strip_tags($html);
        $sent = $this->send($to, $subject, $html, $attachments);

        if (!empty($logContext)) {
            app(IntegrationActionLogService::class)->logEmail(
                organizationId: isset($logContext['organization_id']) ? (int) $logContext['organization_id'] : null,
                clientId: isset($logContext['client_id']) ? (int) $logContext['client_id'] : null,
                action: (string) ($logContext['action'] ?? 'email'),
                recipient: $to,
                subject: $subject,
                payload: [
                    'view' => $view,
                    'data' => $data,
                    'request_body' => $this->mailPayload(
                        $to,
                        $subject,
                        $html,
                        $text,
                        $this->attachmentPayload($attachments, false)
                    ),
                    'email_body' => [
                        'html' => $html,
                        'text' => $text,
                    ],
                ],
                successful: $sent,
                commercialOfferId: isset($logContext['commercial_offer_id'])
                    ? (int) $logContext['commercial_offer_id']
                    : null
            );
        }

        return $sent;
    }

    public function send(string $to, string $subject, string $html, array $attachments = []): bool
    {
        try {
            $response = $this->sendRequest($to, $subject, $html, $attachments);

            if ($response->successful()) {
                return true;
            }

            Log::error('BrevoMailService: failed to send email', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('BrevoMailService: exception sending email', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->sendViaMailFallback($to, $subject, $html, $attachments);
    }

    public function sendRequest(
        string $to,
        string $subject,
        string $html,
        array $attachments = []
    ): Response {
        $this->ensureConfigured();

        return Http::withHeaders([
            'api-key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->post(
            self::ENDPOINT,
            $this->mailPayload(
                $to,
                $subject,
                $html,
                strip_tags($html),
                $this->attachmentPayload($attachments)
            )
        );
    }

    private function ensureConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('BREVO_API_KEY is not configured');
        }

        if ($this->fromEmail === '') {
            throw new RuntimeException('BREVO_MAIL_FROM_ADDRESS is not configured');
        }
    }

    private function sendViaMailFallback(
        string $to,
        string $subject,
        string $html,
        array $attachments
    ): bool {
        try {
            Mail::html($html, function ($message) use ($to, $subject, $attachments): void {
                $message->to($to)->subject($subject);

                foreach ($attachments as $attachment) {
                    $options = [];
                    if (!empty($attachment['mime'])) {
                        $options['mime'] = (string) $attachment['mime'];
                    }

                    $message->attachData(
                        (string) ($attachment['content'] ?? ''),
                        (string) ($attachment['filename'] ?? 'attachment'),
                        $options
                    );
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('BrevoMailService: Mail fallback failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function mailPayload(
        string $to,
        string $subject,
        string $html,
        string $text,
        array $attachments = []
    ): array {
        $payload = [
            'sender' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName,
            ],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
        ];

        if (!empty($attachments)) {
            $payload['attachment'] = $attachments;
        }

        return $payload;
    }

    private function attachmentPayload(array $attachments, bool $includeContent = true): array
    {
        return collect($attachments)
            ->map(function (array $attachment) use ($includeContent): array {
                $payload = [
                    'name' => (string) ($attachment['filename'] ?? 'attachment'),
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
