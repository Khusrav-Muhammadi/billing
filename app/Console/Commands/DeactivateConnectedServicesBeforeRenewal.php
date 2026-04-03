<?php

namespace App\Console\Commands;

use App\Models\ConnectedClientServices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeactivateConnectedServicesBeforeRenewal extends Command
{
    protected $signature = 'connected-services:deactivate-before-renewal
                            {--client_id= : Ограничить обработку одним client_id}
                            {--dry-run : Только показать сколько записей будет изменено}';

    protected $description = 'Отключает (status=0) услуги в connected_client_services, которые были до продления';

    public function handle(): int
    {
        $clientId = $this->option('client_id');
        $dryRun = (bool) $this->option('dry-run');

        $latestRenewalDates = DB::table('connected_client_services as ccs')
            ->join('commercial_offers as co', 'co.id', '=', 'ccs.commercial_offer_id')
            ->whereIn('co.request_type', ['renewal', 'renewal_no_changes'])
            ->whereNotNull('ccs.date')
            ->when($clientId !== null && $clientId !== '', function ($query) use ($clientId) {
                $query->where('ccs.client_id', (int) $clientId);
            })
            ->groupBy('ccs.client_id')
            ->select([
                'ccs.client_id',
                DB::raw('MAX(ccs.date) as latest_renewal_date'),
            ])
            ->get();

        if ($latestRenewalDates->isEmpty()) {
            $this->info('Клиенты с услугами типа renewal не найдены.');
            return self::SUCCESS;
        }

        $totalClients = 0;
        $totalRows = 0;

        foreach ($latestRenewalDates as $row) {
            $targetClientId = (int) $row->client_id;
            $latestRenewalDate = (string) $row->latest_renewal_date;

            $query = ConnectedClientServices::query()
                ->where('client_id', $targetClientId)
                ->where('status', true)
                ->whereNotNull('date')
                ->where('date', '<', $latestRenewalDate);

            $affected = (int) $query->count();
            if ($affected <= 0) {
                continue;
            }

            $totalClients++;
            $totalRows += $affected;

            if (!$dryRun) {
                $query->update(['status' => false]);
            }

            $this->line(sprintf(
                'client_id=%d latest_renewal_date=%s affected=%d%s',
                $targetClientId,
                $latestRenewalDate,
                $affected,
                $dryRun ? ' (dry-run)' : ''
            ));
        }

        if ($totalRows === 0) {
            $this->info('Изменений не требуется: активных услуг до продления не найдено.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s Готово. clients=%d rows=%d',
            $dryRun ? '[DRY-RUN]' : '',
            $totalClients,
            $totalRows
        ));

        return self::SUCCESS;
    }
}
