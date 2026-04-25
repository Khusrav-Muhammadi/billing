<?php

namespace App\Console\Commands;

use App\Models\ConnectedClientServices;
use App\Models\OrganizationConnectionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AddPacks extends Command
{
    protected $signature = 'add:packs';
    protected $description = '';

    private const TYPES_WITH_AMOUNT = [
        'add_user',
        'add_sales_funnel',
        'add_channel',
        'add_insta_channel',
        'add_mini_app_b2b',
        'add_mini_app_b2c',
    ];

    public function handle(): void
    {
        $organizationIds = OrganizationConnectionStatus::where('status', 'connected')
            ->pluck('organization_id')
            ->toArray();

        $connectedClients = ConnectedClientServices::with(['tariff', 'organization.client'])
            ->whereIn('client_id', $organizationIds)
            ->where('tariff_id', '>', 4)
            ->get();

        $domain = env('APP_DOMAIN');

        foreach ($connectedClients as $connectedClient) {
            $tariff = $connectedClient->tariff;

            $client = $connectedClient->organization?->client;
            if (!$client || !$client->sub_domain) {
                $this->warn("Skipping client_id={$connectedClient->client_id}: missing organization or sub_domain");
                continue;
            }

            $url = 'https://' . $client->sub_domain . '-back.' . $domain . '/api/organization/add-pack';

            $data = [
                'type'             => $tariff->type,
                'b_organization_id' => $connectedClient->client_id,
            ];

            if (in_array($tariff->type, self::TYPES_WITH_AMOUNT)) {
                $data['amount'] = $connectedClient->quantity;
            }

            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->post($url, $data);

            if ($response->failed()) {
                $this->error("Failed for client_id={$connectedClient->client_id}, status={$response->status()}");
            }
        }
    }
}
