<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\Tariff;
use App\Services\IntegrationActionLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Organization $organization, public int $tariff_id, public string $domain)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = config('services.sham.domain');
        $url = "https://{$this->domain}-back.{$domain}/api/organization/update-tariff";

        $tariff = Tariff::query()
            ->whereKey($this->tariff_id)
            ->where('is_tariff', true)
            ->firstOrFail();

        $payload = [
            'b_organization_id' => $this->organization->id,
            'tariff_id' => $tariff->id,
            'user_count' => $tariff->user_count,
            'channels_count' => $tariff->channels_count ?? 3,
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post($url, $payload);

        app(IntegrationActionLogService::class)->logApiResponse(
            organizationId: (int)$this->organization->id,
            clientId: (int)($this->organization->client_id ?? 0),
            action: 'connection_update_tariff',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response
        );
    }
}
