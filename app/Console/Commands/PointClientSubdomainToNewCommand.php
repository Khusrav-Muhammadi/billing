<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * clients.sub_domain у старых клиентов был без -new (acme),
 * а живой тенант после переезда — acme-new-back.
 * Биллинг собирает API как {sub_domain}-back, поэтому пишем acme-new.
 *
 * Клиента без тенанта {sub}-new-back не трогаем: у него нет пары,
 * и новое демо тоже остаётся без -new.
 */
class PointClientSubdomainToNewCommand extends Command
{
    protected $signature = 'clients:point-subdomain-to-new
        {--execute : Записать sub_domain. Без флага только план}
        {--from= : Первая буква sub_domain от, например a}
        {--to= : Первая буква sub_domain до, включительно, например d}
        {--crm-database=shamcrm : База CRM, где лежит таблица tenants}';

    protected $description = 'Прописать clients.sub_domain с -new там, где в CRM есть тенант *-new-back';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $from = strtolower(trim((string) $this->option('from')));
        $to = strtolower(trim((string) $this->option('to')));

        $this->info($execute
            ? 'Пишем sub_domain.'
            : 'Сухой прогон. Добавь --execute чтобы записать.');

        $newTenantIds = $this->newTenantIds((string) $this->option('crm-database'));
        if ($newTenantIds === null) {
            return self::FAILURE;
        }

        $updated = 0;
        $skipped = 0;

        DB::table('clients')
            ->whereNotNull('sub_domain')
            ->where('sub_domain', '!=', '')
            ->orderBy('id')
            ->select(['id', 'sub_domain'])
            ->chunkById(200, function ($clients) use ($newTenantIds, $from, $to, $execute, &$updated, &$skipped) {
                foreach ($clients as $client) {
                    $current = strtolower(trim((string) $client->sub_domain));
                    $next = $this->nextSubdomain($current);
                    if ($next === null) {
                        continue;
                    }

                    $first = substr($current, 0, 1);
                    if ($from !== '' && strcmp($first, $from) < 0) {
                        continue;
                    }
                    if ($to !== '' && strcmp($first, $to) > 0) {
                        continue;
                    }

                    // Пары нет — клиент живёт на своём -back, -new не дописываем.
                    if (!isset($newTenantIds[$next . '-back'])) {
                        continue;
                    }

                    if (DB::table('clients')->where('sub_domain', $next)->where('id', '!=', $client->id)->exists()) {
                        $this->warn("Пропуск {$current}: {$next} уже занят другим клиентом.");
                        $skipped++;
                        continue;
                    }

                    $this->line("{$current} → {$next}");
                    if ($execute) {
                        DB::table('clients')->where('id', $client->id)->update([
                            'sub_domain' => $next,
                            'updated_at' => now(),
                        ]);
                        $this->renameDemoRequests($current, $next);
                    }
                    $updated++;
                }
            });

        $this->info(($execute ? 'Обновлено: ' : 'Будет обновлено: ') . $updated . ", пропущено из-за занятого адреса: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>|null
     */
    private function newTenantIds(string $database): ?array
    {
        $crm = array_merge(config('database.connections.mysql'), [
            'database' => $database,
        ]);
        config(['database.connections.crm_tenants' => $crm]);
        DB::purge('crm_tenants');

        try {
            $ids = DB::connection('crm_tenants')
                ->table('tenants')
                ->where('id', 'like', '%-new-back')
                ->pluck('id');
        } catch (\Throwable $e) {
            $this->error('Не удалось прочитать tenants из ' . $database . ': ' . $e->getMessage());

            return null;
        }

        $set = [];
        foreach ($ids as $id) {
            $set[(string) $id] = true;
        }

        $this->line('Тенантов *-new-back в CRM: ' . count($set));

        return $set;
    }

    private function nextSubdomain(string $current): ?string
    {
        if ($current === '' || str_ends_with($current, '-new') || str_ends_with($current, '-back')) {
            return null;
        }

        return $current . '-new';
    }

    private function renameDemoRequests(string $current, string $next): void
    {
        if (!Schema::hasTable('demo_requests') || !Schema::hasColumn('demo_requests', 'sub_domain')) {
            return;
        }

        DB::table('demo_requests')->where('sub_domain', $current)->update([
            'sub_domain' => $next,
            'updated_at' => now(),
        ]);
    }
}
