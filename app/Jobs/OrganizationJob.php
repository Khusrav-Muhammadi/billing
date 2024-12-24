<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\Tariff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class OrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Organization $organization, public string $domain)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
//        $url = "https://$this->domain-back.shamcrm.com/api/organization";
        $url = "https://e33c-95-142-94-22.ngrok-free.app/api/organization";

        $organization = $this->organization;
        $tariff = Tariff::find($organization->client->tariff_id);

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, [
            'name' => $organization->name,
            'tariff_id' => $tariff->id,
            'lead_count' => $tariff->lead_count,
            'user_count' => $tariff->user_count,
            'project_count' => $tariff->project_count,
            'b_organization_id' => $organization->id
        ]);
    }
}
