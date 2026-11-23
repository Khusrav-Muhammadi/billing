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
 * Код подтверждения привязки существующего демо к партнёру.
 * Письмо уходит на почту демо-аккаунта: так доказываем, что аккаунт принадлежит партнёру.
 */
class SendPartnerDemoLinkCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $email,
        public string $code,
        public string $partnerName,
        public string $crmHost,
        public int $ttl,
    ) {
    }

    public function handle(): void
    {
        $data = ['code' => $this->code, 'partnerName' => $this->partnerName, 'crmHost' => $this->crmHost, 'ttl' => $this->ttl];
        $subject = 'Код подтверждения shamCRM: ' . $this->code;

        try {
            $sent = app(BrevoMailService::class)->sendWithView(
                to: $this->email,
                subject: $subject,
                view: 'mail.partner_demo_link_code',
                data: $data,
                sendInternalCopy: false,
                logContext: ['action' => 'partner_demo_link_code'],
            );

            if ($sent) {
                return;
            }
        } catch (\Throwable $e) {
            Log::error('SendPartnerDemoLinkCodeJob: Brevo failed, falling back to Mail', ['error' => $e->getMessage()]);
        }

        Mail::send('mail.partner_demo_link_code', $data, function ($message) use ($subject) {
            $message->to($this->email)->subject($subject);
        });
    }
}
