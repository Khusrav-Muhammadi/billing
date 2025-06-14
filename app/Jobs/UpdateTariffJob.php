<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class UpdateTariffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Client $client, public int $tariff_id, public string $domain)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = env('APP_DOMAIN');
        $url = "https://{$this->domain}-back.{$domain}/api/organization/update-tariff";

        $organizations = $this->client->organizations()->get();
        foreach ($organizations as $organization) {
            $tariff = TariffCurrency::query()->where('tariff_id', $this->tariff_id)->first()->tarifd_id;


            Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($url, [
                'b_organization_id' => $organization->id,
                'tariff_id' => $this->tariff_id,
                'lead_count' => $tariff->lead_count,
                'user_count' => $tariff->user_count,
                'project_count' => $tariff->project_count,
            ]);
        }
    }
}
