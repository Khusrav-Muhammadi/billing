<?php

namespace App\Jobs;

use App\Events\ClientHistoryEvent;
use App\Events\OrganizationHistoryEvent;
use App\Mail\ChangeRequestStatusMail;
use App\Mail\DemoWelcomeMail;
use App\Mail\NewRequestMail;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Partner;
use App\Models\PartnerRequest;
use App\Models\Tariff;
use App\Models\User;
use App\Services\IntegrationActionLogService;
use App\Services\WithdrawalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;


class SendDemoWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function handle(): void
    {
        $successful = true;
        $error = null;

        try {
            Mail::to($this->client->email)->send(new DemoWelcomeMail($this->client));
        } catch (\Throwable $e) {
            $successful = false;
            $error = $e->getMessage();
            throw $e;
        } finally {
            $organization = $this->client->organizations()->first();
            app(IntegrationActionLogService::class)->logEmail(
                organizationId: $organization?->id ? (int)$organization->id : null,
                clientId: (int)$this->client->id,
                action: 'demo_welcome_email',
                recipient: (string)$this->client->email,
                subject: 'Добро пожаловать в shamCRM',
                payload: [
                    'client_id' => $this->client->id,
                    'client_name' => $this->client->name,
                    'sub_domain' => $this->client->sub_domain,
                ],
                successful: $successful,
                error: $error
            );
        }
    }
}
