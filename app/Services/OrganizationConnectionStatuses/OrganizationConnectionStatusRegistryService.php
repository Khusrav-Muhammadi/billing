<?php

namespace App\Services\OrganizationConnectionStatuses;

use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use App\Support\RegistryDateTimeResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrganizationConnectionStatusRegistryService
{
    public function registerConnected(CommercialOffer $offer, CommercialOfferStatus $status): void
    {
        if ((string) $status->status !== 'paid') {
            return;
        }

        $requestType = (string) ($offer->request_type ?: 'connection');
        if (!in_array($requestType, ['connection', 'renewal', 'renewal_no_changes'], true)) {
            return;
        }

        $organizationId = (int) ($offer->organization_id ?? 0);
        if ($organizationId <= 0) {
            return;
        }

        $statusDateTime = RegistryDateTimeResolver::resolve($offer, $status);

        DB::transaction(function () use ($organizationId, $offer, $status, $statusDateTime, $requestType): void {
            Organization::query()
                ->where('id', $organizationId)
                ->update([
                    'has_access' => true,
                    'updated_at' => now(),
                ]);

            OrganizationConnectionStatus::query()->updateOrCreate(
                [
                    'commercial_offer_id' => (int) $offer->id,
                ],
                [
                    'organization_id' => $organizationId,
                    'status' => 'connected',
                    'status_date' => $statusDateTime,
                    'day_closing_id' => null,
                    'author_id' => $status->author_id ? (int) $status->author_id : null,
                    'reason' => $requestType,
                ]
            );
        });
    }

    public function registerDisconnected(
        int $organizationId,
        Carbon $statusDateTime,
        ?int $dayClosingId = null,
        ?int $authorId = null,
        ?string $reason = null
    ): void {
        if ($organizationId <= 0) {
            return;
        }

        DB::transaction(function () use ($organizationId, $statusDateTime, $dayClosingId, $authorId, $reason): void {
            $updated = Organization::query()
                ->where('id', $organizationId)
                ->where('has_access', true)
                ->update([
                    'has_access' => false,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                return;
            }

            $exists = OrganizationConnectionStatus::query()
                ->where('organization_id', $organizationId)
                ->where('status', 'disconnected')
                ->where('status_date', $statusDateTime->format('Y-m-d H:i:s'))
                ->exists();

            if ($exists) {
                return;
            }

            OrganizationConnectionStatus::query()->create([
                'organization_id' => $organizationId,
                'status' => 'disconnected',
                'status_date' => $statusDateTime,
                'commercial_offer_id' => null,
                'day_closing_id' => $dayClosingId,
                'author_id' => $authorId,
                'reason' => $reason,
            ]);
        });
    }
}

