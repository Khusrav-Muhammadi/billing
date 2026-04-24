<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillConnectedClientServicesQuantity extends Command
{
    protected $signature = 'connected-services:backfill-quantity
                            {--client_id= : Ограничить обработку одним client_id}
                            {--offer_id= : Ограничить обработку одним commercial_offer_id}
                            {--dry-run : Только показать изменения без записи}';

    protected $description = 'Заполняет connected_client_services.quantity из commercial_offer_items.quantity (sum по offer+tariff)';

    public function handle(): int
    {
        if (!Schema::hasTable('connected_client_services') || !Schema::hasColumn('connected_client_services', 'quantity')) {
            $this->error('В таблице connected_client_services нет колонки quantity. Сначала выполните миграции.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('commercial_offer_items')) {
            $this->error('Таблица commercial_offer_items не найдена.');
            return self::FAILURE;
        }

        $dryRun = (bool)$this->option('dry-run');

        $clientId = $this->option('client_id');
        $clientId = $clientId !== null && $clientId !== '' ? (int)$clientId : null;

        $offerId = $this->option('offer_id');
        $offerId = $offerId !== null && $offerId !== '' ? (int)$offerId : null;

        $query = DB::table('connected_client_services')
            ->whereNotNull('commercial_offer_id')
            ->where('commercial_offer_id', '!=', 0)
            ->whereNotNull('tariff_id')
            ->where('tariff_id', '!=', 0)
            ->when($clientId, fn($q) => $q->where('client_id', $clientId))
            ->when($offerId, fn($q) => $q->where('commercial_offer_id', $offerId));

        $scanned = 0;
        $resolved = 0;
        $missing = 0;
        $updated = 0;

        $query
            ->orderBy('id')
            ->select(['id', 'commercial_offer_id', 'tariff_id', 'quantity'])
            ->chunkById(500, function ($rows) use (&$scanned, &$resolved, &$missing, &$updated, $dryRun): void {
                $rows = collect($rows);
                $scanned += $rows->count();

                $offerIds = $rows
                    ->pluck('commercial_offer_id')
                    ->map(fn($id) => (int)$id)
                    ->filter(fn(int $id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if (empty($offerIds)) {
                    return;
                }

                $items = DB::table('commercial_offer_items')
                    ->whereIn('commercial_offer_id', $offerIds)
                    ->whereNotNull('tariff_id')
                    ->where('tariff_id', '!=', 0)
                    ->select(['commercial_offer_id', 'tariff_id', 'quantity'])
                    ->get();

                $qtyMap = [];
                foreach ($items as $item) {
                    $mapKey = (int)$item->commercial_offer_id . ':' . (int)$item->tariff_id;
                    $qtyMap[$mapKey] = ($qtyMap[$mapKey] ?? 0.0) + (float)($item->quantity ?? 0);
                }

                foreach ($rows as $row) {
                    $ccsId = (int)$row->id;
                    $offerId = (int)$row->commercial_offer_id;
                    $tariffId = (int)$row->tariff_id;
                    if ($ccsId <= 0 || $offerId <= 0 || $tariffId <= 0) {
                        continue;
                    }

                    $mapKey = $offerId . ':' . $tariffId;
                    $nextQty = null;
                    if (array_key_exists($mapKey, $qtyMap)) {
                        $resolved++;
                        $nextQty = max(1, (int)round((float)$qtyMap[$mapKey]));
                    } else {
                        $missing++;
                        $nextQty = 1;
                    }

                    $currentQty = $row->quantity !== null ? (int)$row->quantity : null;
                    if ($currentQty === $nextQty) {
                        continue;
                    }

                    $updated++;
                    if (!$dryRun) {
                        DB::table('connected_client_services')
                            ->where('id', $ccsId)
                            ->update([
                                'quantity' => $nextQty,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });

        $this->info(sprintf(
            '%sconnected_client_services.quantity backfill: scanned=%d resolved=%d missing=%d updated=%d',
            $dryRun ? '[DRY-RUN] ' : '',
            $scanned,
            $resolved,
            $missing,
            $updated
        ));

        return self::SUCCESS;
    }
}

