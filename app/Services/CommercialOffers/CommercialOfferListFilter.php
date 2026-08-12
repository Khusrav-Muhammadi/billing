<?php

namespace App\Services\CommercialOffers;

use Illuminate\Database\Eloquent\Builder;

class CommercialOfferListFilter
{
    public function apply(Builder $query, array $filters): Builder
    {
        $partnerId = (int) ($filters['partner_id'] ?? 0);
        $requestType = trim((string) ($filters['request_type'] ?? ''));
        $tariffId = (int) ($filters['tariff_id'] ?? 0);
        $periodMonths = (int) ($filters['period_months'] ?? 0);
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $operationStatus = trim((string) ($filters['operation_status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

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

        if (in_array($operationStatus, ['draft', 'paid', 'canceled'], true)) {
            $this->applyOperationStatus($query, $operationStatus);
        }

        if ($search !== '') {
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
            'partner_id',
            'request_type',
            'tariff_id',
            'period_months',
            'date_from',
            'date_to',
            'operation_status',
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

    private function applyOperationStatus(Builder $query, string $status): void
    {
        if (in_array($status, ['paid', 'canceled'], true)) {
            $query->where(function ($statusQuery) use ($status) {
                $statusQuery->whereExists(function ($subQuery) use ($status) {
                    $subQuery->selectRaw('1')
                        ->from('commercial_offer_statuses as latest_status')
                        ->whereColumn('latest_status.commercial_offer_id', 'commercial_offers.id')
                        ->where('latest_status.status', $status)
                        ->whereRaw(
                            'latest_status.id = (
                                select max(cos_max.id)
                                from commercial_offer_statuses as cos_max
                                where cos_max.commercial_offer_id = commercial_offers.id
                            )'
                        );
                })->orWhere(function ($fallbackQuery) use ($status) {
                    $fallbackQuery->where('commercial_offers.status', $status)
                        ->whereDoesntHave('offerStatuses');
                });
            });

            return;
        }

        // draft: как на странице — нет pending/paid/canceled и нет locked/payment_link
        $query->where(function ($draftQuery) {
            $draftQuery
                ->where(function ($latestQuery) {
                    $latestQuery->whereDoesntHave('offerStatuses')
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->selectRaw('1')
                                ->from('commercial_offer_statuses as latest_status')
                                ->whereColumn('latest_status.commercial_offer_id', 'commercial_offers.id')
                                ->whereNotIn('latest_status.status', ['pending', 'paid', 'canceled'])
                                ->whereRaw(
                                    'latest_status.id = (
                                        select max(cos_max.id)
                                        from commercial_offer_statuses as cos_max
                                        where cos_max.commercial_offer_id = commercial_offers.id
                                    )'
                                );
                        });
                })
                ->where(function ($offerStatusQuery) {
                    $offerStatusQuery
                        ->whereNull('commercial_offers.status')
                        ->orWhereNotIn('commercial_offers.status', [
                            'pending',
                            'paid',
                            'canceled',
                            'payment_link_generated',
                        ]);
                })
                ->whereNull('commercial_offers.locked_at');
        });
    }
}
