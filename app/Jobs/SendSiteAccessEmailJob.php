<?php

namespace App\Jobs;

use App\Mail\SendSiteDataMail;
use App\Models\Client;
use App\Models\Organization;
use App\Services\Mailing\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSiteAccessEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Client $client, public Organization $organization, public string $password)
    {
    }

    public function handle(): void
    {
        if (Organization::where('client_id', $this->client->id)->count() > 1) {
            return;
        }

        try {
            $brevo = app(BrevoMailService::class);

            $sent = $brevo->sendWithView(
                to: $this->client->email,
                subject: 'Подключение к системе "shamCRM"',
                view: 'mail.send_site_data',
                data: [
                    'client' => $this->client,
                    'password' => $this->password,
                    'id' => $this->organization->order_number,
                ],
                logContext: [
                    'organization_id' => $this->organization->id,
                    'client_id' => $this->client->id,
                    'action' => 'site_access_email',
                ]
            );

            if ($sent) {
                return;
            }
        } catch (\Throwable $e) {
            Log::error('SendSiteAccessEmailJob: Brevo failed, falling back to Mail', [
                'client_id' => $this->client->id,
                'email' => $this->client->email,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::to($this->client->email)->send(new SendSiteDataMail($this->client, $this->password));
        } catch (\Throwable $e) {
            Log::error('SendSiteAccessEmailJob: failed to send welcome email', [
                'client_id' => $this->client->id,
                'email' => $this->client->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
