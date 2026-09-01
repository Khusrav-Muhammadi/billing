<?php

namespace App\Services\Mailing;

use App\Services\IntegrationActionLogService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SafeBrevoMailService extends BrevoMailService
{
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
        $text = $this->renderText($view, $data, $html);
        $sent = $this->send($to, $subject, $html, $attachments, $text);

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
                    'request_body' => $this->payload(
                        $to,
                        $subject,
                        $html,
                        $text,
                        $this->attachmentsWithoutContent($attachments)
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

    public function send(string $to, string $subject, string $html, array $attachments = [], ?string $text = null): bool
    {
        $text ??= MailText::fromHtml($html);

        try {
            $response = $this->sendRequest($to, $subject, $html, $attachments, $text);

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

        return $this->fallback($to, $subject, $html, $attachments);
    }

    public function sendRequest(
        string $to,
        string $subject,
        string $html,
        array $attachments = [],
        ?string $text = null
    ): Response {
        $apiKey = (string) config('services.brevo.api_key');
        $fromEmail = (string) config('services.brevo.mail_from');

        if ($apiKey === '') {
            throw new RuntimeException('BREVO_API_KEY is not configured');
        }

        if ($fromEmail === '') {
            throw new RuntimeException('BREVO_MAIL_FROM_ADDRESS is not configured');
        }

        return Http::withHeaders([
            'api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->post(
            self::ENDPOINT,
            $this->payload(
                $to,
                $subject,
                $html,
                $text ?? MailText::fromHtml($html),
                $this->attachmentsWithContent($attachments)
            )
        );
    }

    public function htmlToText(string $html): string
    {
        return MailText::fromHtml($html);
    }

    private function renderText(string $view, array $data, string $html): string
    {
        $textView = $view . '_text';

        if (view()->exists($textView)) {
            return view($textView, $data)->render();
        }

        return MailText::fromHtml($html);
    }

    private function fallback(string $to, string $subject, string $html, array $attachments): bool
    {
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

    private function payload(
        string $to,
        string $subject,
        string $html,
        string $text,
        array $attachments = []
    ): array {
        $payload = [
            'sender' => [
                'email' => (string) config('services.brevo.mail_from'),
                'name' => (string) config('services.brevo.mail_from_name', 'shamCRM'),
            ],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
            'headers' => [
                'X-Mailin-Track' => 'false',
                'X-Mailin-Track-Clicks' => 'false',
            ],
        ];

        if (!empty($attachments)) {
            $payload['attachment'] = $attachments;
        }

        return $payload;
    }

    private function attachmentsWithContent(array $attachments): array
    {
        return collect($attachments)
            ->map(function (array $attachment): array {
                return [
                    'name' => (string) ($attachment['filename'] ?? 'attachment'),
                    'content' => base64_encode((string) ($attachment['content'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    private function attachmentsWithoutContent(array $attachments): array
    {
        return collect($attachments)
            ->map(fn (array $attachment): array => [
                'name' => (string) ($attachment['filename'] ?? 'attachment'),
            ])
            ->values()
            ->all();
    }
}
