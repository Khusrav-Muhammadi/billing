<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Всем clients.sub_domain дописывает -new.
 * Уже оканчивающиеся на -new не трогает, чтобы не получилось -new-new.
 */
class PointClientSubdomainToNewCommand extends Command
{
    protected $signature = 'clients:point-subdomain-to-new
        {--execute : Записать. Без флага только показывает, сколько строк изменится}';

    protected $description = 'Дописать -new ко всем clients.sub_domain';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');

        $rows = DB::table('clients')
            ->whereNotNull('sub_domain')
            ->where('sub_domain', '!=', '')
            ->where('sub_domain', 'not like', '%-new')
            ->get(['id', 'sub_domain']);

        $taken = DB::table('clients')
            ->whereNotNull('sub_domain')
            ->pluck('sub_domain')
            ->map(fn ($name) => strtolower((string) $name))
            ->flip();

        $ids = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $next = strtolower((string) $row->sub_domain) . '-new';

            if (isset($taken[$next])) {
                $this->warn("Пропуск {$row->sub_domain}: {$next} уже есть");
                $skipped++;
                continue;
            }

            $taken[$next] = true;
            $ids[] = $row->id;
        }

        $this->info('Допишем -new: ' . count($ids) . ', пропуск: ' . $skipped);

        if (!$execute) {
            $this->line('Сухой прогон. Добавь --execute чтобы записать.');

            return self::SUCCESS;
        }

        $updated = 0;
        foreach (array_chunk($ids, 200) as $chunk) {
            $updated += DB::table('clients')
                ->whereIn('id', $chunk)
                ->update([
                    'sub_domain' => DB::raw("CONCAT(sub_domain, '-new')"),
                    'updated_at' => now(),
                ]);
        }

        $this->info('Обновлено: ' . $updated);

        return self::SUCCESS;
    }
}
