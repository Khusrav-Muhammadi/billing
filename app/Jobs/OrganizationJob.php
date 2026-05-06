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
        $domain = config('services.sham.domain');
        $url = "https://{$this->domain}-back.{$domain}/api/organization";

        $organization = $this->organization;
        $tariff = Tariff::query()
            ->whereKey($organization->client->tariff_id)
            ->where('is_tariff', true)
            ->firstOrFail();

        Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, [
            'name' => $organization->name,
            'tariff_id' => $tariff->id,
            'user_count' => $tariff->user_count,
            'project_count' => $tariff->project_count,
            'b_organization_id' => $organization->id
        ]);
    }
}
