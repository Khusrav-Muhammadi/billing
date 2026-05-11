<?php

namespace App\Jobs;

use App\Mail\SendSiteDataMail;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Tariff;
use App\Services\IntegrationActionLogService;
use App\Services\Mailing\ResendMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateOrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Client $client, public Organization $organization, public string $password)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = config('services.sham.domain');
        $url = "https://{$this->client->sub_domain}-back.{$domain}/api/organization";

        $tariff = Tariff::query()
            ->where('id', 4)
            ->first();

        $payload = [
            'name' => $this->organization->name,
            'email' => $this->client->email,
            'phone' => $this->client->phone,
            'tariff_id' => $tariff->id,
            'user_count' => $tariff->user_count,
            'project_count' => $tariff->project_count,
            'b_organization_id' => $this->organization->id,
            'password' => $this->password,
            'is_demo' => $this->client->is_demo,
            'channels_count' => $tariff->channels_count ?? 3,
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($url, $payload);
        } catch (\Throwable $e) {
            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: (int)$this->organization->id,
                clientId: (int)$this->client->id,
                action: 'create_organization',
                method: 'POST',
                url: $url,
                payload: $payload,
                error: $e->getMessage()
            );

            return;
        }

        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: (int)$this->organization->id,
            clientId: (int)$this->client->id,
            action: 'create_organization',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response
        );

        if (Organization::where('client_id', $this->client->id)->count() === 1) {
            $this->sendWelcomeEmail();
        }
    }

    private function sendWelcomeEmail(): void
    {
        try {
            $resend = new ResendMailService();

            $resend->sendWithView(
                to:      $this->client->email,
                subject: 'Подключение к системе "shamCRM"',
                view:    'mail.send_site_data',
                data:    [
                    'client'   => $this->client,
                    'password' => $this->password,
                    'id' => $this->organization->order_number
                ],
                logContext: [
                    'organization_id' => $this->organization->id,
                    'client_id' => $this->client->id,
                    'action' => 'site_access_email',
                ]
            );

        } catch (\Throwable $e) {
            Log::error('CreateOrganizationJob: failed to send welcome email', [
                'client_id' => $this->client->id,
                'email'     => $this->client->email,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
