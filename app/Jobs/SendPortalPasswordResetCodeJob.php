<?php

namespace App\Jobs;

use App\Services\Mailing\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Код для сброса пароля в партнёрском портале (см. PortalPasswordResetService).
 * Brevo, при ошибке — обычный Mail, как SendPartnerDemoLinkCodeJob.
 */
class SendPortalPasswordResetCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $email,
        public string $code,
        public string $name,
        public int $ttl,
    ) {
    }

    public function handle(): void
    {
        $data = ['code' => $this->code, 'name' => $this->name, 'ttl' => $this->ttl];
        $subject = 'Код для сброса пароля shamCRM Partners: ' . $this->code;

        try {
            $sent = app(BrevoMailService::class)->sendWithView(
                to: $this->email,
                subject: $subject,
                view: 'mail.portal_password_reset_code',
                data: $data,
                sendInternalCopy: false,
                logContext: ['action' => 'portal_password_reset_code'],
            );

            if ($sent) {
                return;
            }
        } catch (\Throwable $e) {
            Log::error('SendPortalPasswordResetCodeJob: Brevo failed, falling back to Mail', ['error' => $e->getMessage()]);
        }

        Mail::send('mail.portal_password_reset_code', $data, function ($message) use ($subject) {
            $message->to($this->email)->subject($subject);
        });
    }
}
