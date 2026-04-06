<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeRegistryPeriodPrices extends Command
{
    protected $signature = 'registry:normalize-period-prices
                            {--offer_id= : Ограничить обработку одним commercial_offer_id}
                            {--dry-run : Только показать изменения без записи}';

    protected $description = 'Нормализует unit_price/registry суммы: unit_price хранится за месяц, total_price — за период';

    public function handle(): int
    {
        $offerId = $this->option('offer_id');
        $dryRun = (bool) $this->option('dry-run');

        $filterOfferId = $offerId !== null && $offerId !== '' ? (int) $offerId : null;

        $this->normalizeCommercialOfferItems($filterOfferId, $dryRun);
        $this->normalizeConnectedClientServices($filterOfferId, $dryRun);

        return self::SUCCESS;
    }

    private function normalizeCommercialOfferItems(?int $offerId, bool $dryRun): void
    {
        $query = DB::table('commercial_offer_items')
            ->where('months', '>', 1);

        if ($offerId) {
            $query->where('commercial_offer_id', $offerId);
        }

        $scanned = 0;
        $updated = 0;

        $query
            ->orderBy('id')
            ->select(['id', 'commercial_offer_id', 'quantity', 'unit_price', 'months', 'total_price'])
            ->chunkById(500, function ($rows) use (&$scanned, &$updated, $dryRun): void {
                foreach ($rows as $row) {
                    $scanned++;

                    $months = max(1, (int) ($row->months ?? 1));
                    $qty = max(1.0, (float) ($row->quantity ?? 1));
                    $unitPrice = round((float) ($row->unit_price ?? 0), 4);
                    $totalPrice = round((float) ($row->total_price ?? 0), 4);

                    if ($months <= 1 || $totalPrice <= 0 || $unitPrice <= 0) {
                        continue;
                    }

                    $periodPerUnit = $qty > 0 ? round($totalPrice / $qty, 4) : $totalPrice;
                    $monthlyPerUnit = $qty > 0 ? round($totalPrice / ($qty * $months), 4) : $totalPrice;

                    // Buggy case: unit_price equals period-per-unit (not monthly).
                    if (abs($unitPrice - $periodPerUnit) > 0.0001) {
                        continue;
                    }

                    // Already monthly.
                    if (abs($unitPrice - $monthlyPerUnit) <= 0.0001) {
                        continue;
                    }

                    $updated++;
                    if (!$dryRun) {
                        DB::table('commercial_offer_items')
                            ->where('id', (int) $row->id)
                            ->update([
                                'unit_price' => $monthlyPerUnit,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        $this->info(sprintf(
            '%scommercial_offer_items: scanned=%d updated=%d',
            $dryRun ? '[DRY-RUN] ' : '',
            $scanned,
            $updated
        ));
    }

    private function normalizeConnectedClientServices(?int $offerId, bool $dryRun): void
    {
        $query = DB::table('connected_client_services as ccs')
            ->join('commercial_offer_items as coi', function ($join) {
                $join->on('coi.commercial_offer_id', '=', 'ccs.commercial_offer_id')
                    ->on('coi.tariff_id', '=', 'ccs.tariff_id');
            })
            ->join('commercial_offers as co', 'co.id', '=', 'ccs.commercial_offer_id')
            ->where('coi.months', '>', 1);

        if ($offerId) {
            $query->where('ccs.commercial_offer_id', $offerId);
        }

        $scanned = 0;
        $updated = 0;

        $query
            ->orderBy('ccs.id')
            ->select([
                'ccs.id as ccs_id',
                'ccs.service_total_amount as current_service_total_amount',
                'ccs.payable_amount as current_payable_amount',
                'co.currency as offer_currency',
                'co.payable_currency as payable_currency',
                'co.conversion_rate as conversion_rate',
                'coi.quantity as quantity',
                'coi.months as months',
                'coi.discount_percent as discount_percent',
                'coi.total_price as total_price',
            ])
            ->chunkById(500, function ($rows) use (&$scanned, &$updated, $dryRun): void {
                foreach ($rows as $row) {
                    $scanned++;

                    $months = max(1, (int) ($row->months ?? 1));
                    $periodTotal = round((float) ($row->total_price ?? 0), 4);

                    if ($periodTotal <= 0 || $months <= 1) {
                        continue;
                    }

                    // service_total_amount stores monthly list totals (before period discounts).
                    $discountPercent = round(max(0, (float) ($row->discount_percent ?? 0)), 4);
                    $periodGrossTotal = $this->reversePercent($periodTotal, $discountPercent);
                    $monthlyTotal = round($periodGrossTotal / $months, 4);

                    $rate = (float) ($row->conversion_rate ?? 0);
                    $offerCurrency = (string) ($row->offer_currency ?? '');
                    $payableCurrency = (string) ($row->payable_currency ?: $offerCurrency);

                    $payableMonthlyTotal = $monthlyTotal;
                    if ($payableCurrency !== '' && $offerCurrency !== '' && $payableCurrency !== $offerCurrency && $rate > 0) {
                        $payableMonthlyTotal = round($payableMonthlyTotal / $rate, 4);
                    }

                    $currentMonthlyTotal = round((float) ($row->current_service_total_amount ?? 0), 4);
                    $currentPayable = round((float) ($row->current_payable_amount ?? 0), 4);

                    $needsUpdate = abs($currentMonthlyTotal - $monthlyTotal) > 0.0001
                        || abs($currentPayable - $payableMonthlyTotal) > 0.0001;

                    if (!$needsUpdate) {
                        continue;
                    }

                    $updated++;
                    if (!$dryRun) {
                        DB::table('connected_client_services')
                            ->where('id', (int) $row->ccs_id)
                            ->update([
                                'service_total_amount' => $monthlyTotal,
                                'payable_amount' => $payableMonthlyTotal,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }, 'ccs.id', 'ccs_id');

        $this->info(sprintf(
            '%sconnected_client_services: scanned=%d updated=%d',
            $dryRun ? '[DRY-RUN] ' : '',
            $scanned,
            $updated
        ));
    }

    private function reversePercent(float $amount, float $percent): float
    {
        if ($percent <= 0 || $percent >= 100) {
            return $amount;
        }

        $factor = 1 - ($percent / 100);
        if ($factor <= 0) {
            return $amount;
        }

        return $amount / $factor;
    }
}
