<?php

namespace App\Services\Site;

use App\Models\ClientBalance;
use App\Models\Organization;
use App\Services\Organizations\OrganizationValidityService;
use Illuminate\Support\Carbon;

class SiteOrganizationStatusService
{
    public function __construct(
        private readonly SiteOrganizationContextService $organizationContext,
        private readonly OrganizationValidityService $validity,
    ) {
    }

    public function build(int $organizationId): array
    {
        $context = $this->organizationContext->resolve($organizationId);
        /** @var Organization $organization */
        $organization = $context['organization'];
        $organization->load([
            'client.partner:id,name',
            'latestConnection',
            'connectedServices' => function ($query) {
                $query->where('status', true)->whereNull('deactivated_at');
            },
            'connectedServices.tariff:id,name,is_tariff,is_extra_user,user_count',
        ]);

        $client = $organization->client;
        $isDemo = (bool) ($client?->is_demo);
        $connectionStatus = (string) ($organization->latestConnection?->status ?? '');
        $tariff = $organization->appTariff;
        $extraUsers = $this->countExtraUsers($organization);
        $validUntil = $this->resolveValidUntil($organization, $isDemo, $client?->created_at);
        $daysLeft = $validUntil
            ? (int) max(0, now()->startOfDay()->diffInDays($validUntil->copy()->startOfDay(), false))
            : null;

        $status = $this->resolveStatus($isDemo, $client?->created_at, $connectionStatus, (bool) $organization->has_access);

        return [
            'organization' => [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
            ],
            'balance' => $this->realBalance($organization, (int) ($client?->country?->currency_id ?? 0)),
            'currency' => $context['currency'],
            'tariff' => $tariff ? [
                'id' => (int) $tariff->id,
                'name' => (string) $tariff->name,
                'users' => (int) ($tariff->user_count ?? 0),
                'extra_users' => $extraUsers,
                'total_users' => (int) ($tariff->user_count ?? 0) + $extraUsers,
            ] : null,
            'valid_until' => $validUntil?->toDateString(),
            'days_left' => $daysLeft,
            'partner' => $this->partnerPayload($client?->partner),
            'status' => $status,
            'is_demo' => $isDemo,
            'has_access' => (bool) $organization->has_access,
        ];
    }

    private function resolveStatus(bool $isDemo, mixed $createdAt, string $connectionStatus, bool $hasAccess): string
    {
        if ($isDemo) {
            $demoUntil = Carbon::parse($createdAt)->addDays(14);
            return $demoUntil->isFuture() ? 'demo' : 'demo_expired';
        }

        if ($connectionStatus === 'connected') {
            return $hasAccess ? 'active' : 'connected';
        }

        if ($connectionStatus === 'disconnected') {
            return 'disconnected';
        }

        return 'inactive';
    }

    private function resolveValidUntil(Organization $organization, bool $isDemo, mixed $createdAt): ?Carbon
    {
        if ($isDemo && $createdAt) {
            return Carbon::parse($createdAt)->addDays(14)->startOfDay();
        }

        return $this->validity->calculateValidUntil($organization);
    }

    private function realBalance(Organization $organization, int $currencyId): float
    {
        $query = ClientBalance::query()->where('organization_id', (int) $organization->id);
        if ($currencyId > 0) {
            $query->where('currency_id', $currencyId);
        }

        $income = (float) (clone $query)->where('type', 'income')->sum('sum');
        $outcome = (float) (clone $query)->where('type', 'outcome')->sum('sum');

        return round($income - $outcome, 4);
    }

    private function countExtraUsers(Organization $organization): int
    {
        $count = 0;
        foreach ($organization->connectedServices as $service) {
            if (!$service->tariff || !(bool) $service->tariff->is_extra_user) {
                continue;
            }
            if ($service->status === false || $service->deactivated_at) {
                continue;
            }
            $count += max(1, (int) ($service->quantity ?? 1));
        }

        return $count;
    }

    private function partnerPayload(mixed $partner): ?array
    {
        if (!$partner) {
            return null;
        }

        return [
            'id' => (int) $partner->id,
            'name' => (string) $partner->name,
        ];
    }
}
