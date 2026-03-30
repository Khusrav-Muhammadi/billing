<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;

class BackfillOrganizationOrderNumbers extends Command
{
    protected $signature = 'organizations:backfill-order-number
                            {--rewrite : Полностью пересчитать order_number с 000000001}
                            {--chunk=500 : Размер чанка для обработки}';

    protected $description = 'Заполняет organizations.order_number в формате 000000001 с инкрементом';

    public function handle(): int
    {
        $rewrite = (bool) $this->option('rewrite');
        $chunk = max(1, (int) $this->option('chunk'));

        $nextNumber = 0;

        if ($rewrite) {
            Organization::query()->update(['order_number' => null]);

            $query = Organization::query()
                ->select(['id'])
                ->orderBy('id');
        } else {
            $nextNumber = (int) (Organization::query()
                ->whereNotNull('order_number')
                ->where('order_number', '!=', '')
                ->max('order_number') ?? 0);

            $query = Organization::query()
                ->select(['id'])
                ->where(function ($builder) {
                    $builder->whereNull('order_number')->orWhere('order_number', '');
                })
                ->orderBy('id');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Нет организаций для обновления.');
            return self::SUCCESS;
        }

        $updated = 0;

        $this->info("Найдено организаций для обработки: {$total}");

        $query->chunkById($chunk, function ($organizations) use (&$updated, &$nextNumber) {
            foreach ($organizations as $organization) {
                $nextNumber++;
                $orderNumber = Organization::formatOrderNumber($nextNumber);

                Organization::query()
                    ->whereKey($organization->id)
                    ->update(['order_number' => $orderNumber]);

                $updated++;
            }
        });

        $this->info("Готово. Обновлено организаций: {$updated}");

        return self::SUCCESS;
    }
}
