<?php

namespace App\Services\Storage;

use App\Models\ConnectedClientServices;
use App\Models\Organization;
use App\Models\Tariff;

/**
 * Считает итоговый лимит файлового хранилища организации в GB.
 *
 * Итог = storage_gb текущего тарифа
 *      + storage_gb × quantity по услугам add_storage, включённым в тариф
 *      + storage_gb × quantity по активным оплаченным пакетам add_storage.
 *
 * Берём только активные строки connected_client_services (status = 1):
 * при новом КП старые гасятся, поэтому истёкшие пакеты сами выпадают из суммы.
 */
class StorageQuotaResolver
{
    /**
     * @return array{storage_limit_gb:int, tariff_id:int|null, tariff_gb:int, packages_gb:int}
     */
    public function forOrganization(int $organizationId): array
    {
        $organization = Organization::query()->with('client')->find($organizationId);
        if (! $organization) {
            return ['storage_limit_gb' => 0, 'tariff_id' => null, 'tariff_gb' => 0, 'packages_gb' => 0];
        }

        $activeRows = ConnectedClientServices::query()
            ->with('tariff')
            ->where('client_id', $organizationId)
            ->where('status', true)
            ->get();

        $tariff = $this->resolveTariff($organization, $activeRows);
        $tariffGb = $this->tariffStorageGb($tariff);
        $packagesGb = $this->packagesStorageGb($activeRows);

        return [
            'storage_limit_gb' => $tariffGb + $packagesGb,
            'tariff_id' => $tariff?->id,
            'tariff_gb' => $tariffGb,
            'packages_gb' => $packagesGb,
        ];
    }

    /**
     * Текущий тариф: активная строка с is_tariff = 1 (самая свежая),
     * иначе — tariff_id клиента (так же делает ClientRepository).
     */
    private function resolveTariff(Organization $organization, $activeRows): ?Tariff
    {
        $row = $activeRows
            ->filter(fn (ConnectedClientServices $r) => $r->tariff && (bool) $r->tariff->is_tariff)
            ->sortByDesc('id')
            ->first();

        if ($row) {
            return $row->tariff;
        }

        $clientTariffId = (int) ($organization->client?->tariff_id ?? 0);

        return $clientTariffId > 0 ? Tariff::query()->find($clientTariffId) : null;
    }

    /** GB тарифа + GB услуг add_storage, включённых в тариф. */
    private function tariffStorageGb(?Tariff $tariff): int
    {
        if (! $tariff) {
            return 0;
        }

        $gb = (int) ($tariff->storage_gb ?? 0);

        foreach ($tariff->includedServices as $service) {
            if ($service->type !== Tariff::TYPE_ADD_STORAGE) {
                continue;
            }

            $gb += (int) ($service->storage_gb ?? 0) * max(1, (int) ($service->pivot->quantity ?? 1));
        }

        return $gb;
    }

    /** GB по активным оплаченным пакетам add_storage. */
    private function packagesStorageGb($activeRows): int
    {
        $gb = 0;

        foreach ($activeRows as $row) {
            $tariff = $row->tariff;
            if (! $tariff || $tariff->type !== Tariff::TYPE_ADD_STORAGE) {
                continue;
            }

            $gb += (int) ($tariff->storage_gb ?? 0) * max(1, (int) ($row->quantity ?? 1));
        }

        return $gb;
    }
}
