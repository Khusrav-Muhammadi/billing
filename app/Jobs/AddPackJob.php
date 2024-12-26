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
        $url = "https://$this->domain-back.shamcrm.com/api/organization/add-pack";

        $organization = $this->organizationPack->organization()->first();
        $pack = $this->organizationPack->pack()->first();
        $data = [
            'type' => $pack->type,
            'b_organization_id' => $organization->id,
        ];

        if ($pack->type == 'user') {
            $data['user_count'] = $pack->amount;
        } else {
            $data['lead_count'] = $pack->amount;
        }

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $data);

    }
}
