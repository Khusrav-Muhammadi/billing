<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\Tariff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeleteOrganizationJob implements ShouldQueue
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

        $domain = env('APP_DOMAIN');
        $url = 'https://' . $this->domain . '-back.' . $domain . '/api/organization/delete-from-billing';

        $data = [
            'b_organization_id' => $this->organization->id,
        ];

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->delete($url, $data);

    }
}
