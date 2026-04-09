<?php

namespace App\Services\DayClosings;

use App\Jobs\ActivationJob;
use App\Models\Client;
use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\DayClosing;
use App\Models\DayClosingClientDetails;
use App\Models\DayClosingDetail;
use App\Services\OrganizationConnectionStatuses\OrganizationConnectionStatusRegistryService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DayClosingService
{
    public function __construct(private OrganizationConnectionStatusRegistryService $organizationConnectionStatusRegistryService)
    {
    }

    /**
     * @return Collection<int, DayClosing>
     *
     */
    public function createDocumentsForPeriod(Carbon $dateFrom, Carbon $dateTo, int $authorId): Collection
    {
        $from = $dateFrom->copy()->startOfDay();
        $to = $dateTo->copy()->startOfDay();

        $this->deleteDocumentsForPeriod($from, $to);

        $created = collect();
        $period = CarbonPeriod::create($from, '1 day', $to);

        foreach ($period as $date) {
            $created->push(
                $this->createDocumentForDate(Carbon::parse($date), $authorId)
            );
        }

        return $created;
    }

    private function createDocumentForDate(Carbon $date, int $authorId): DayClosing
    {
        return DB::transaction(function () use ($date, $authorId) {
            $dayClosing = DayClosing::query()->create([
                'date' => $date->copy()->setTime(23, 30, 0),
                'doc_number' => '',
                'author_id' => $authorId,
                'client_amount' => 0,
                'status' => true,
            ]);

            $dayClosing->update([
                'doc_number' => $this->formatDocNumber((int) $dayClosing->id),
            ]);

            $organizationsCount = 0;
            $hasInsufficientBalance = false;
            $deactivationBatchesByClient = [];

            $clients = Client::query()
                ->where('is_active', true)
                ->with([
                    'organizations:id,client_id,name,has_access',
                    'country:id,currency_id',
                ])
                ->select(['id', 'name', 'country_id', 'sub_domain'])
                ->orderBy('id')
                ->get();

            foreach ($clients as $client) {
                foreach ($client->organizations as $organization) {
                    if ($organization->has_access !== null && !(bool) $organization->has_access) {
                        continue;
                    }

                    $services = ConnectedClientServices::query()
                        ->where('client_id', (int) $organization->id)
                        ->where('status', true)
                        ->whereDate('date', '<=', $date->toDateString())
                        ->get([
                            'id',
                            'tariff_id',
                            'service_total_amount',
                            'payable_currency_id',
                            'offer_currency_id',
                        ]);

                    if ($services->isEmpty()) {
                        continue;
                    }

                    $currencyId = (int) ($services->first()->offer_currency_id
                        ?: $services->first()->payable_currency_id
                        ?: ($client->country?->currency_id ?? 0));

                    $services = $this->filterServicesByCurrency($services, $currencyId);
                    if ($services->isEmpty()) {
                        continue;
                    }

                    $daysInMonth = max(1, $date->daysInMonth);
                    $dailyAccrual = 0.0;

                    $serviceRows = [];
                    foreach ($services as $service) {
                        $monthlySum = round((float) $service->service_total_amount, 4);
                        if ($monthlySum <= 0) {
                            continue;
                        }

                        $dailySum = round($monthlySum / $daysInMonth, 4);
                        $dailyAccrual += $dailySum;

                        $serviceRows[] = [
                            'client_id' => (int) $client->id,
                            'tariff_id' => (int) $service->tariff_id,
                            'monthly_sum' => $monthlySum,
                            'daily_sum' => $dailySum,
                        ];
                    }

                    if (empty($serviceRows)) {
                        continue;
                    }

                    $dailyAccrual = round($dailyAccrual, 4);
                    $balanceBeforeAccrual = $this->calculateBalance(
                        (int) $organization->id,
                        $currencyId > 0 ? $currencyId : null
                    );

                    $canAccrue = $dailyAccrual > 0 && $balanceBeforeAccrual >= $dailyAccrual;
                    $balanceAfterAccrual = $canAccrue
                        ? round($balanceBeforeAccrual - $dailyAccrual, 4)
                        : $balanceBeforeAccrual;

                    $detail = DayClosingDetail::query()->create([
                        'day_closing_id' => (int) $dayClosing->id,
                        'organization_id' => (int) $organization->id,
                        'currency_id' => $currencyId > 0 ? $currencyId : null,
                        'balance_before_accrual' => $balanceBeforeAccrual,
                        'balance_after_accrual' => $balanceAfterAccrual,
                        'status_after_accrual' => $canAccrue,
                    ]);

                    foreach ($serviceRows as $row) {
                        DayClosingClientDetails::query()->create([
                            'day_closing_details_id' => (int) $detail->id,
                            'client_id' => $row['client_id'],
                            'tariff_id' => $row['tariff_id'],
                            'monthly_sum' => $row['monthly_sum'],
                            'daily_sum' => $row['daily_sum'],
                        ]);
                    }

                    if ($canAccrue) {
                        ClientBalance::query()->create([
                            'date' => $date->copy()->setTime(23, 30, 0),
                            'organization_id' => (int) $organization->id,
                            'sum' => $dailyAccrual,
                            'currency_id' => $currencyId > 0 ? $currencyId : null,
                            'type' => 'outcome',
                        ]);
                    } else {
                        $hasInsufficientBalance = true;
                        $this->organizationConnectionStatusRegistryService->registerDisconnected(
                            (int) $organization->id,
                            $dayClosing->date ?: $date->copy()->setTime(23, 30, 0),
                            (int) $dayClosing->id,
                            $authorId,
                            'insufficient_balance'
                        );

                        $clientId = (int) $client->id;
                        $subDomain = trim((string) ($client->sub_domain ?? ''));
                        if ($clientId > 0 && $subDomain !== '') {
                            if (!isset($deactivationBatchesByClient[$clientId])) {
                                $deactivationBatchesByClient[$clientId] = [
                                    'sub_domain' => $subDomain,
                                    'organization_ids' => [],
                                ];
                            }

                            $deactivationBatchesByClient[$clientId]['organization_ids'][] = (int) $organization->id;
                        }
                    }

                    $organizationsCount++;
                }
            }

            foreach ($deactivationBatchesByClient as $batch) {
                $organizationIds = array_values(array_unique(array_map('intval', (array) ($batch['organization_ids'] ?? []))));
                $subDomain = trim((string) ($batch['sub_domain'] ?? ''));

                if (empty($organizationIds) || $subDomain === '') {
                    continue;
                }

                ActivationJob::dispatch(
                    $organizationIds,
                    $subDomain,
                    false,
                    true,
                    $authorId,
                    'insufficient_balance'
                );
            }

            $dayClosing->update([
                'client_amount' => $organizationsCount,
                'status' => !$hasInsufficientBalance,
            ]);

            return $dayClosing;
        });
    }

    private function deleteDocumentsForPeriod(Carbon $dateFrom, Carbon $dateTo): void
    {
        DB::transaction(function () use ($dateFrom, $dateTo): void {
            $dayClosings = DayClosing::query()
                ->whereDate('date', '>=', $dateFrom->toDateString())
                ->whereDate('date', '<=', $dateTo->toDateString())
                ->get(['id', 'date']);

            $dayClosingIds = $dayClosings->pluck('id');

            if ($dayClosingIds->isEmpty()) {
                return;
            }

            // Remove all previous day-closing outcomes for these documents' timestamps.
            // Without this, repeated reruns leave duplicate balance outcomes.
            $dayClosingDateTimes = $dayClosings
                ->map(fn (DayClosing $dayClosing) => $dayClosing->date?->format('Y-m-d H:i:s'))
                ->filter()
                ->unique()
                ->values();

            if ($dayClosingDateTimes->isNotEmpty()) {
                ClientBalance::query()
                    ->where('type', 'outcome')
                    ->whereIn('date', $dayClosingDateTimes->all())
                    ->delete();
            }

            $detailIds = DayClosingDetail::query()
                ->whereIn('day_closing_id', $dayClosingIds->all())
                ->pluck('id');

            if ($detailIds->isNotEmpty()) {
                DayClosingClientDetails::query()
                    ->whereIn('day_closing_details_id', $detailIds->all())
                    ->delete();
            }

            DayClosingDetail::query()
                ->whereIn('day_closing_id', $dayClosingIds->all())
                ->delete();

            DayClosing::query()
                ->whereIn('id', $dayClosingIds->all())
                ->forceDelete();
        });
    }

    private function formatDocNumber(int $id): string
    {
        return str_pad((string) max(1, $id), 9, '0', STR_PAD_LEFT);
    }

    /**
     * @param Collection<int, ConnectedClientServices> $services
     * @return Collection<int, ConnectedClientServices>
     */
    private function filterServicesByCurrency(Collection $services, int $currencyId): Collection
    {
        if ($currencyId <= 0) {
            return $services->values();
        }

        $filtered = $services
            ->filter(function (ConnectedClientServices $service) use ($currencyId): bool {
                $serviceCurrencyId = (int) ($service->offer_currency_id ?: $service->payable_currency_id ?: 0);

                return $serviceCurrencyId === 0 || $serviceCurrencyId === $currencyId;
            })
            ->values();

        if ($filtered->isNotEmpty()) {
            return $filtered;
        }

        return $services->values();
    }

    private function calculateBalance(int $organizationId, ?int $currencyId): float
    {
        $query = ClientBalance::query()->where('organization_id', $organizationId);
        if (!empty($currencyId)) {
            $query->where('currency_id', $currencyId);
        }

        $income = (clone $query)->where('type', 'income')->sum('sum');
        $outcome = (clone $query)->where('type', 'outcome')->sum('sum');

        return round((float) $income - (float) $outcome, 4);
    }
}
