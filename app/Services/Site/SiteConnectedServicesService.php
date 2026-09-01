<?php

namespace App\Services\Site;

use App\Models\ConnectedClientServices;
use App\Models\Tariff;
use Illuminate\Support\Collection;

class SiteConnectedServicesService
{
    public function __construct(
        private readonly SiteOrganizationContextService $organizationContext,
    ) {
    }

    public function build(int $organizationId): array
    {
        $context = $this->organizationContext->resolve($organizationId);
        $organization = $context['organization'];

        $rows = ConnectedClientServices::query()
            ->where('client_id', $organization->id)
            ->where('status', true)
            ->whereNull('deactivated_at')
            ->whereNotNull('date')
            ->whereDate('date', '<=', now()->toDateString())
            ->with(['tariff:id,name,is_tariff,is_extra_user,can_increase,user_count,is_one_time,one_time_label,category'])
            ->orderBy('id')
            ->get();

        $tariffRow = $rows->first(function (ConnectedClientServices $row): bool {
            $tariff = $row->tariff;

            return $tariff && (bool) $tariff->is_tariff && !(bool) $tariff->is_extra_user;
        });

        $tariff = $tariffRow?->tariff;
        if ($tariff) {
            $tariff->loadMissing([
                'includedServices:id,name,is_tariff,is_extra_user,can_increase',
            ]);
        }

        return [
            'organization' => [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
            ],
            'tariff' => $this->tariffPayload($tariff),
            'extra_users' => $this->extraUsersPayload($rows),
            'services' => $this->servicesPayload($rows, $tariff),
        ];
    }

    private function tariffPayload(?Tariff $tariff): ?array
    {
        if (!$tariff) {
            return null;
        }

        return [
            'id' => (int) $tariff->id,
            'name' => (string) $tariff->name,
            'users' => (int) ($tariff->user_count ?? 0),
            'quantity' => 1,
            'category' => $this->nullableString($tariff->category),
        ];
    }

    private function extraUsersPayload(Collection $rows): array
    {
        $quantity = 0;
        $item = null;

        foreach ($rows as $row) {
            $tariff = $row->tariff;
            if (!$tariff || !(bool) $tariff->is_extra_user) {
                continue;
            }

            $quantity += $this->quantity($row);
            $item ??= $tariff;
        }

        return [
            'id' => $item ? (int) $item->id : null,
            'name' => $item ? (string) $item->name : 'Доп. пользователи',
            'quantity' => $quantity,
        ];
    }

    private function servicesPayload(Collection $rows, ?Tariff $tariff): array
    {
        $includedById = [];
        if ($tariff) {
            foreach ($tariff->includedServices as $included) {
                $includedById[(int) $included->id] = [
                    'quantity' => max(1, (int) ($included->pivot?->quantity ?? 1)),
                    'is_paid' => (bool) ($included->pivot?->is_paid ?? false),
                ];
            }
        }

        $services = [];
        foreach ($rows as $row) {
            $service = $row->tariff;
            if (!$service || (bool) $service->is_tariff || (bool) $service->is_extra_user) {
                continue;
            }

            $id = (int) $service->id;
            if (!isset($services[$id])) {
                $included = $includedById[$id] ?? null;
                $services[$id] = [
                    'id' => $id,
                    'name' => (string) $service->name,
                    'quantity' => 0,
                    'can_increase' => (bool) $service->can_increase,
                    'is_one_time' => (bool) ($service->is_one_time ?? false),
                    'included' => $included !== null,
                    'included_quantity' => $included['quantity'] ?? 0,
                    'included_is_paid' => $included['is_paid'] ?? false,
                    'category' => $this->nullableString($service->category),
                ];
            }

            $services[$id]['quantity'] += $this->quantity($row);
        }

        foreach ($includedById as $id => $included) {
            if (isset($services[$id])) {
                continue;
            }

            $service = $tariff?->includedServices->firstWhere('id', $id);
            if (!$service) {
                continue;
            }

            $services[$id] = [
                'id' => $id,
                'name' => (string) $service->name,
                'quantity' => (int) $included['quantity'],
                'can_increase' => (bool) $service->can_increase,
                'is_one_time' => (bool) ($service->is_one_time ?? false),
                'included' => true,
                'included_quantity' => (int) $included['quantity'],
                'included_is_paid' => (bool) $included['is_paid'],
                'category' => $this->nullableString($service->category),
            ];
        }

        return array_values($services);
    }

    private function quantity(ConnectedClientServices $row): int
    {
        $qty = $row->quantity;

        return $qty === null ? 1 : max(1, (int) $qty);
    }

    private function nullableString(mixed $value): ?string
    {
        $raw = trim((string) $value);

        return $raw !== '' ? $raw : null;
    }
}
