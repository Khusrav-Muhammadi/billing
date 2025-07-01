<?php

namespace App\Jobs;

use App\Events\ClientHistoryEvent;
use App\Events\OrganizationHistoryEvent;
use App\Mail\NewErrorMail;
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

class NewErrorMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $message, public array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to('amr_1990@mail.ru')->send(new NewErrorMail($this->message, $this->data));
    }
}
