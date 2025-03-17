<?php

namespace App\Jobs;

use App\Events\ClientHistoryEvent;
use App\Events\OrganizationHistoryEvent;
use App\Mail\NewRequestMail;
use App\Mail\NewSiteRequestMail;
use App\Models\Organization;
use App\Models\OrganizationPack;
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

class NewSiteRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public User $user, public string $type)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new NewSiteRequestMail($this->user, $this->type));
    }
}
