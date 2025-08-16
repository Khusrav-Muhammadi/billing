<?php

namespace App\Jobs;

use App\Events\ClientHistoryEvent;
use App\Events\OrganizationHistoryEvent;
use App\Mail\ChangeRequestStatusMail;
use App\Mail\ExistingClientInfoMail;
use App\Mail\NewRequestMail;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Partner;
use App\Models\PartnerRequest;
use App\Models\Tariff;
use App\Models\User;
use App\Services\WithdrawalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class EmailExistingClientInfoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function handle(): void
    {
        Mail::to($this->client->email)->send(new ExistingClientInfoMail($this->client));
    }
}
