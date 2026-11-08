<?php

namespace App\Support;

use App\Models\ConnectedClientServices;
use App\Models\Organization;

/**
 * Активные пакеты организации для update-tariff в CRM.
 * Без них CRM при смене тарифа или продлении снимает права пакетов (склад, магазин, SMS).
 */
class CrmPackTypes
{
    /**
     * @return list<string>
     */
    public static function activeFor(Organization $organization): array
    {
        return ConnectedClientServices::query()
            ->join('tariffs', 'tariffs.id', '=', 'connected_client_services.tariff_id')
            ->where('connected_client_services.client_id', $organization->id)
            ->where('connected_client_services.status', true)
            ->whereNotNull('tariffs.type')
            ->where('tariffs.type', '!=', '')
            ->distinct()
            ->orderBy('tariffs.type')
            ->pluck('tariffs.type')
            ->map(fn ($type) => trim((string) $type))
            ->values()
            ->all();
    }
}
