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

        $count = DB::table('clients')
            ->whereNotNull('sub_domain')
            ->where('sub_domain', '!=', '')
            ->where('sub_domain', 'not like', '%-new')
            ->count();

        $this->info('Строк без -new: ' . $count);

        if (!$execute) {
            $this->line('Сухой прогон. Добавь --execute чтобы записать.');

            return self::SUCCESS;
        }

        $updated = DB::table('clients')
            ->whereNotNull('sub_domain')
            ->where('sub_domain', '!=', '')
            ->where('sub_domain', 'not like', '%-new')
            ->update([
                'sub_domain' => DB::raw("CONCAT(sub_domain, '-new')"),
                'updated_at' => now(),
            ]);

        $this->info('Обновлено: ' . $updated);

        return self::SUCCESS;
    }
}
