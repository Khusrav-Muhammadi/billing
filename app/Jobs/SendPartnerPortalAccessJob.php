<?php

namespace App\Jobs;

use App\Mail\SendPartnerDataMail;
use App\Models\User;
use App\Services\Mailing\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Логин и пароль партнёрского портала после саморегистрации.
 * Как SendSiteAccessEmailJob: сначала Brevo, при сбое — обычная почта.
 */
class SendPartnerPortalAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public User $partner, public string $password)
    {
    }

    public function handle(): void
    {
        try {
            $sent = app(BrevoMailService::class)->sendWithView(
                to: $this->partner->email,
                subject: 'Доступ в партнёрский кабинет shamCRM',
                view: 'mail.send_partner_data',
                data: ['partner' => $this->partner, 'password' => $this->password],
                logContext: ['partner_id' => $this->partner->id, 'action' => 'partner_portal_access_email'],
            );

            if ($sent) {
                return;
            }
        } catch (\Throwable $e) {
            Log::error('SendPartnerPortalAccessJob: Brevo failed, falling back to Mail', [
                'partner_id' => $this->partner->id,
                'error' => $e->getMessage(),
            ]);
        }

        Mail::to($this->partner->email)->send(new SendPartnerDataMail($this->partner, $this->password));
    }
}
