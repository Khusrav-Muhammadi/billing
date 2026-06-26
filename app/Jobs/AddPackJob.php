<?php

namespace App\Jobs;

use App\Models\ConnectedClientServices;
use App\Models\Organization;
use App\Services\IntegrationActionLogService;
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
    public function __construct(
        public Organization $organization,
        public string $sub_domain,
        public ?int $commercialOfferId = null
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = config('services.sham.domain');
        $url = 'https://' . $this->sub_domain . '-back.' . $domain . '/api/organization/add-pack';

        $connectedClients = ConnectedClientServices::with(['tariff', 'organization.client'])
            ->where('client_id', $this->organization->id)
            ->where('tariff_id', '>', 4)
            ->where('status', 1)
            ->get();

        $grouped = [];
        $offerIds = [];

        foreach ($connectedClients as $connectedClient) {
            $tariff = $connectedClient->tariff;
            if (!$tariff || !$tariff->type) {
                continue;
            }

            $type = $tariff->type;
            $quantity = max(1, (int)round((float)($connectedClient->quantity ?? 1)));

            if (!isset($grouped[$type])) {
                $grouped[$type] = 0;
            }
            $grouped[$type] += $quantity;
            $offerIds[$type] = $connectedClient->commercial_offer_id;
        }

        foreach ($grouped as $type => $totalQuantity) {
            $data = [
                'type' => $type,
                'b_organization_id' => $this->organization->id,
            ];

            $data['amount'] = $totalQuantity;

            if ($type == 'add_user') {
                $data['user_count'] = $totalQuantity;
            }

            if ($type == 'add_sales_funnel') {
                $data['sales_funnel_count'] = $totalQuantity;
            }

            if (in_array($type, ['add_channel', 'add_insta_channel', 'add_mini_app_b2b', 'add_mini_app_b2c'], true)) {
                $data['channels_count'] = $totalQuantity;
                $data['channel'] = $totalQuantity;
            }

            $commercialOfferId = isset($offerIds[$type]) ? (int)$offerIds[$type] : null;

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                ])->post($url, $data);
            } catch (\Throwable $e) {
                app(IntegrationActionLogService::class)->logApiResponse(
                    organizationId: (int)$this->organization->id,
                    clientId: (int)($this->organization->client_id ?? 0),
                    action: 'add_pack',
                    method: 'POST',
                    url: $url,
                    payload: $data,
                    error: $e->getMessage(),
                    commercialOfferId: $commercialOfferId
                );

                continue;
            }

            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: (int)$this->organization->id,
                clientId: (int)($this->organization->client_id ?? 0),
                action: 'add_pack',
                method: 'POST',
                url: $url,
                payload: $data,
                response: $response,
                commercialOfferId: $commercialOfferId
            );
        }
    }
}
