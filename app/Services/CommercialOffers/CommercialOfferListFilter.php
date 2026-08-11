<?php

namespace App\Services\CommercialOffers;

use Illuminate\Database\Eloquent\Builder;

class CommercialOfferListFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        $organizationId = (int) ($filters['organization_id'] ?? 0);
        $partnerId = (int) ($filters['partner_id'] ?? 0);
        $requestType = trim((string) ($filters['request_type'] ?? ''));
        $tariffId = (int) ($filters['tariff_id'] ?? 0);
        $periodMonths = (int) ($filters['period_months'] ?? 0);
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($organizationId > 0) {
            $query->where('organization_id', $organizationId);
        }

        if ($partnerId > 0) {
            $query->where('partner_id', $partnerId);
        }

        if ($requestType !== '' && in_array($requestType, [
            'connection',
            'connection_extra_services',
            'renewal',
            'renewal_no_changes',
        ], true)) {
            $query->where('request_type', $requestType);
        }

        if ($tariffId > 0) {
            $query->where('tariff_id', $tariffId);
        }

        if (in_array($periodMonths, [6, 12], true)) {
            $query->where('period_months', $periodMonths);
        }

        if ($dateFrom !== '') {
            $query->whereDate('status_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('status_date', '<=', $dateTo);
        }

        if ($search !== '' && $organizationId <= 0) {
            $query->whereHas('organization', function ($organizationQuery) use ($search) {
                $organizationQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function activeQueryParams(array $filters): array
    {
        $params = [];

        foreach ([
            'organization_id',
            'partner_id',
            'request_type',
            'tariff_id',
            'period_months',
            'date_from',
            'date_to',
            'search',
        ] as $key) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === '' || $value === 0 || $value === '0') {
                continue;
            }
            $params[$key] = $value;
        }

        return $params;
    }
}
