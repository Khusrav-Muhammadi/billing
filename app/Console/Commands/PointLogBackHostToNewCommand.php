<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * В логах интеграции хост CRM был {tenant}-back.
 * Повтор запроса берёт url из лога как есть, поэтому старый sham-back
 * уходит на мёртвый адрес. Здесь дописываем -new: sham-back → sham-new-back.
 * Уже -new-back не трогаем, чтобы не вышло -new-new-back.
 */
class PointLogBackHostToNewCommand extends Command
{
    protected $signature = 'logs:point-back-host-to-new
        {--execute : Записать. Без флага только показывает, что изменится}';

    protected $description = 'В логах API дописать -new в хост: sham-back → sham-new-back';

    private const COLUMNS = ['url', 'error', 'payload', 'response'];

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $changed = 0;
        $shown = 0;

        DB::table('integration_action_logs')
            ->where(function ($query): void {
                foreach (self::COLUMNS as $column) {
                    $query->orWhere($column, 'like', '%-back.%');
                }
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($execute, &$changed, &$shown): void {
                foreach ($rows as $row) {
                    $update = [];

                    foreach (self::COLUMNS as $column) {
                        $current = $row->{$column};
                        if (!is_string($current) || $current === '') {
                            continue;
                        }

                        $next = self::rewrite($current);
                        if ($next === $current) {
                            continue;
                        }

                        $update[$column] = $next;
                    }

                    if ($update === []) {
                        continue;
                    }

                    $changed++;

                    if ($shown < 20 && isset($update['url'])) {
                        $this->line("#{$row->id} {$row->url}");
                        $this->line("    → {$update['url']}");
                        $shown++;
                    }

                    if (!$execute) {
                        continue;
                    }

                    $update['updated_at'] = now();
                    DB::table('integration_action_logs')
                        ->where('id', $row->id)
                        ->update($update);
                }
            });

        $this->info('Строк с новым хостом: ' . $changed);

        if (!$execute) {
            $this->line('Сухой прогон. Добавь --execute чтобы записать.');
        }

        return self::SUCCESS;
    }

    /**
     * sham-back.shamcrm.com → sham-new-back.shamcrm.com.
     * fingroupcrm-new-back остаётся как есть.
     */
    public static function rewrite(string $value): string
    {
        $next = preg_replace('/(?<!-new)-back\./', '-new-back.', $value);

        return is_string($next) ? $next : $value;
    }
}
