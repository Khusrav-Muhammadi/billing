<?php

namespace App\Services\Organizations;

use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OrganizationValidityService
{
    public function hydrate(Collection|LengthAwarePaginator $organizations, ?Carbon $asOf = null): void
    {
        $items = $organizations instanceof LengthAwarePaginator
            ? $organizations->getCollection()
            : $organizations;

        foreach ($items as $organization) {
            $organization->setAttribute(
                'calculated_valid_until',
                $this->calculateValidUntil($organization, $asOf)
            );
        }
    }

    public function calculateValidUntil(Organization $organization, ?Carbon $asOf = null): ?Carbon
    {
        $asOf = ($asOf ?: now())->copy()->endOfDay();

        if (!$this->hasActiveConnection($organization, $asOf)) {
            return null;
        }

        $services = $this->activeServices($organization, $asOf);
        if ($services->isEmpty()) {
            return null;
        }

        $client = $organization->client;
        $currencyId = (int)($services->first()->offer_currency_id
            ?: $services->first()->payable_currency_id
                ?: ($client?->country?->currency_id ?? 0));

        $services = $this->filterServicesByCurrency($services, $currencyId);
        if ($services->isEmpty()) {
            return null;
        }

        $dailyRate = $this->dailyRate($services, $asOf);
        if ($dailyRate <= 0) {
            return null;
        }

        $balance = $this->calculateBalance(
            (int)$organization->id,
            $currencyId > 0 ? $currencyId : null,
            $asOf
        );

        $daysLeft = (int)floor(max(0, $balance) / $dailyRate);

        return $asOf->copy()->startOfDay()->addDays($daysLeft);
    }

    private function hasActiveConnection(Organization $organization, Carbon $asOf): bool
    {
        $latestConnection = OrganizationConnectionStatus::query()
            ->where('organization_id', (int)$organization->id)
            ->where('status_date', '<=', $asOf->format('Y-m-d H:i:s'))
            ->orderByDesc('status_date')
            ->orderByDesc('updated_at')
            ->first(['id', 'status']);

        return (string)($latestConnection?->status ?? '') === 'connected';
    }

    private function activeServices(Organization $organization, Carbon $asOf): Collection
    {
        $date = $asOf->toDateString();

        return ConnectedClientServices::query()
            ->where('client_id', (int)$organization->id)
            ->whereDate('date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('deactivated_at')
                    ->orWhereDate('deactivated_at', '>=', $date);
            })
            ->get([
                'id',
                'tariff_id',
                'service_total_amount',
                'payable_currency_id',
                'offer_currency_id',
            ]);
    }

    private function filterServicesByCurrency(Collection $services, int $currencyId): Collection
    {
        if ($currencyId <= 0) {
            return $services->values();
        }

        $filtered = $services
            ->filter(function (ConnectedClientServices $service) use ($currencyId): bool {
                $serviceCurrencyId = (int)($service->offer_currency_id ?: $service->payable_currency_id ?: 0);

                return $serviceCurrencyId === 0 || $serviceCurrencyId === $currencyId;
            })
            ->values();

        return $filtered->isNotEmpty() ? $filtered : $services->values();
    }

    private function dailyRate(Collection $services, Carbon $asOf): float
    {
        $daysInMonth = max(1, $asOf->daysInMonth);
        $dailyRate = 0.0;

        foreach ($services as $service) {
            $monthlySum = round((float)$service->service_total_amount, 4);
            if ($monthlySum <= 0) {
                continue;
            }

            $dailyRate += round($monthlySum / $daysInMonth, 4);
        }

        return round($dailyRate, 4);
    }

    private function calculateBalance(int $organizationId, ?int $currencyId, Carbon $asOf): float
    {
        $query = ClientBalance::query()
            ->where('organization_id', $organizationId)
            ->where('date', '<=', $asOf->format('Y-m-d H:i:s'));

        if (!empty($currencyId)) {
            $query->where('currency_id', $currencyId);
        }

        $income = (clone $query)->where('type', 'income')->sum('sum');
        $outcome = (clone $query)->where('type', 'outcome')->sum('sum');

        return round((float)$income - (float)$outcome, 4);
    }
}
