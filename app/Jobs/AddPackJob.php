<?php

namespace App\Jobs;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\ConnectedClientServices;
use App\Models\Organization;
use App\Services\IntegrationActionLogService;
use App\Support\RegistryDateTimeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends pack quantities to CRM (/api/organization/add-pack).
 * CRM API is additive — never send the org-wide total of all historical packs.
 *
 * - Default: only rows of the given commercial offer (connection / extra services).
 * - sendOnlyIncreases: for renewal — send max(0, new − just-deactivated previous) per type.
 */
class AddPackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Organization $organization,
        public string $sub_domain,
        public int $commercialOfferId,
        public bool $sendOnlyIncreases = false,
    ) {
    }

    public function handle(): void
    {
        if ($this->commercialOfferId <= 0) {
            throw new RuntimeException(
                'AddPackJob: commercial_offer_id is required (CRM add-pack is additive; cannot send org-wide totals).'
            );
        }

        $domain = config('services.sham.domain');
        if (! is_string($domain) || trim($domain) === '') {
            throw new RuntimeException('AddPackJob: services.sham.domain is not configured.');
        }

        $url = 'https://' . $this->sub_domain . '-back.' . $domain . '/api/organization/add-pack';

        $quantitiesByType = $this->sendOnlyIncreases
            ? $this->resolveIncreaseQuantitiesByType()
            : $this->resolveOfferQuantitiesByType();

        if ($quantitiesByType === []) {
            return;
        }

        foreach ($quantitiesByType as $type => $totalQuantity) {
            if ($totalQuantity <= 0) {
                continue;
            }

            $data = [
                'type' => $type,
                'b_organization_id' => $this->organization->id,
                'amount' => $totalQuantity,
            ];

            if ($type === 'add_user') {
                $data['user_count'] = $totalQuantity;
            }

            if ($type === 'add_sales_funnel') {
                $data['sales_funnel_count'] = $totalQuantity;
            }

            if (in_array($type, ['add_channel', 'add_insta_channel', 'add_mini_app_b2b', 'add_mini_app_b2c'], true)) {
                $data['channels_count'] = $totalQuantity;
                $data['channel'] = $totalQuantity;
            }

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                ])->post($url, $data);
            } catch (\Throwable $e) {
                app(IntegrationActionLogService::class)->logApiResponse(
                    organizationId: (int) $this->organization->id,
                    clientId: (int) ($this->organization->client_id ?? 0),
                    action: 'add_pack',
                    method: 'POST',
                    url: $url,
                    payload: $data,
                    error: $e->getMessage(),
                    commercialOfferId: $this->commercialOfferId
                );

                throw new RuntimeException(
                    "AddPackJob: CRM add-pack failed for org #{$this->organization->id}, type={$type}: {$e->getMessage()}",
                    0,
                    $e
                );
            }

            app(IntegrationActionLogService::class)->logApiResponse(
                organizationId: (int) $this->organization->id,
                clientId: (int) ($this->organization->client_id ?? 0),
                action: 'add_pack',
                method: 'POST',
                url: $url,
                payload: $data,
                response: $response,
                commercialOfferId: $this->commercialOfferId
            );

            if (! $response->successful()) {
                throw new RuntimeException(
                    "AddPackJob: CRM add-pack HTTP {$response->status()} for org #{$this->organization->id}, type={$type}: {$response->body()}"
                );
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function resolveOfferQuantitiesByType(): array
    {
        $rows = ConnectedClientServices::with(['tariff'])
            ->where('client_id', $this->organization->id)
            ->where('commercial_offer_id', $this->commercialOfferId)
            ->where('tariff_id', '>', 4)
            ->where('status', 1)
            ->get();

        return $this->groupQuantitiesByType($rows);
    }

    /**
     * Renewal: previous packs stay on CRM; only send positive delta vs just-deactivated rows.
     *
     * @return array<string, int>
     */
    private function resolveIncreaseQuantitiesByType(): array
    {
        $newByType = $this->resolveOfferQuantitiesByType();
        if ($newByType === []) {
            return [];
        }

        $offer = CommercialOffer::query()->find($this->commercialOfferId);
        if (! $offer) {
            throw new RuntimeException(
                "AddPackJob: commercial offer #{$this->commercialOfferId} not found for delta calculation."
            );
        }

        $paidStatus = CommercialOfferStatus::query()
            ->where('commercial_offer_id', $this->commercialOfferId)
            ->where('status', 'paid')
            ->orderByDesc('id')
            ->first();

        if (! $paidStatus) {
            throw new RuntimeException(
                "AddPackJob: paid status missing for offer #{$this->commercialOfferId}; cannot compute pack delta."
            );
        }

        $deactivatedAt = RegistryDateTimeResolver::resolve($offer, $paidStatus);

        $previousRows = ConnectedClientServices::with(['tariff'])
            ->where('client_id', $this->organization->id)
            ->where('commercial_offer_id', '!=', $this->commercialOfferId)
            ->where('tariff_id', '>', 4)
            ->where('status', false)
            ->where('deactivated_at', $deactivatedAt)
            ->get();

        $oldByType = $this->groupQuantitiesByType($previousRows);

        $delta = [];
        foreach ($newByType as $type => $newQty) {
            $oldQty = (int) ($oldByType[$type] ?? 0);
            $increase = $newQty - $oldQty;
            if ($increase > 0) {
                $delta[$type] = $increase;
            }
        }

        return $delta;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ConnectedClientServices>  $rows
     * @return array<string, int>
     */
    private function groupQuantitiesByType($rows): array
    {
        $grouped = [];

        foreach ($rows as $connectedClient) {
            $tariff = $connectedClient->tariff;
            if (! $tariff) {
                throw new RuntimeException(
                    "AddPackJob: tariff missing for connected_client_services #{$connectedClient->id}."
                );
            }

            $type = trim((string) ($tariff->type ?? ''));
            if ($type === '') {
                throw new RuntimeException(
                    "AddPackJob: tariff #{$tariff->id} has empty type; cannot provision pack to CRM."
                );
            }

            $quantity = max(1, (int) round((float) ($connectedClient->quantity ?? 1)));
            $grouped[$type] = ($grouped[$type] ?? 0) + $quantity;
        }

        return $grouped;
    }
}
