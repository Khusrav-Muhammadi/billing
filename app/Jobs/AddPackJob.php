<?php

namespace App\Jobs;

use App\Models\OrganizationPack;
use App\Models\Tariff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class AddPackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public OrganizationPack $organizationPack, public string $domain)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
//        $url = "https://$this->domain-back.shamcrm.com/api/organization/add-pack";
        $url = "https://e33c-95-142-94-22.ngrok-free.app/api/organization/add-pack";

        $organization = $this->organizationPack->organization()->first();
        $pack = $this->organizationPack->pack()->first();

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, [
            'type' => $pack->type,
            'amount' => $pack->amount,
            'b_organization_id' => $organization->id
        ]);
    }
}
