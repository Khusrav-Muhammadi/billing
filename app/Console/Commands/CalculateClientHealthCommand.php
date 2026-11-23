<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ClientHealth\ClientHealthContextService;
use App\Services\ClientHealth\ClientHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Индекс здоровья клиента (светофор): ночной расчёт за вчера.
 *
 *   php artisan app:client-health-calculate                      — за вчера, все клиенты
 *   php artisan app:client-health-calculate --date=2026-11-20    — за конкретный день
 *   php artisan app:client-health-calculate --days=35            — история за 35 дней по вчера
 *   php artisan app:client-health-calculate --client=12 --client=15
 *
 * Дни идут от старого к новому: падение «за 2 недели» сравнивает с уже посчитанным днём D-14.
 */
class CalculateClientHealthCommand extends Command
{
    protected $signature = 'app:client-health-calculate
        {--date= : Отчётный день Y-m-d (по умолчанию вчера)}
        {--days=1 : Сколько дней посчитать, заканчивая отчётным днём}
        {--client=* : Только эти клиенты (clients.id)}';

    protected $description = 'Индекс здоровья клиентов: расчёт и история по дням';

    public function handle(ClientHealthService $service, ClientHealthContextService $context): int
    {
        $end = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : now()->subDay()->startOfDay();
        if ($end->gte(now()->startOfDay())) {
            $this->error('Отчётный день должен быть в прошлом: сегодняшний день ещё не закончился.');

            return self::FAILURE;
        }

        $days = max(1, min(120, (int) $this->option('days')));
        $clientIds = array_filter(array_map('intval', (array) $this->option('client')));

        $clients = $context->candidates()
            ->when($clientIds, fn ($q) => $q->whereIn('id', $clientIds))
            ->orderBy('id')
            ->get();

        $done = 0;
        $skipped = 0;
        $failed = 0;

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $end->copy()->subDays($i);

            foreach ($clients as $client) {
                /** @var Client $client */
                try {
                    $snapshot = $service->calculate($client, $date);
                    $snapshot ? $done++ : $skipped++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::warning('client-health: расчёт не удался', [
                        'client_id' => $client->id,
                        'sub_domain' => $client->sub_domain,
                        'date' => $date->toDateString(),
                        'error' => $e->getMessage(),
                    ]);
                    $this->warn("#{$client->id} {$client->sub_domain} {$date->toDateString()}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Посчитано: {$done}, пропущено (не платный и не демо): {$skipped}, ошибок: {$failed}.");

        return self::SUCCESS;
    }
}
