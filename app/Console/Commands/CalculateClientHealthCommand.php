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

    /** Больше этой доли недоступных CRM за запуск — общий сбой, красными не отмечаем. */
    private const MAX_FAILED_SHARE = 0.2;

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
        /** @var array<int, array{client: Client, error: string, date: string}> $failed CRM не ответила: один раз на клиента за запуск */
        $failed = [];
        /** @var array<int, true> $ok клиенты, у которых CRM ответила хотя бы раз */
        $ok = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $end->copy()->subDays($i);

            foreach ($clients as $client) {
                /** @var Client $client */
                if (isset($failed[$client->id])) {
                    continue; // CRM этого клиента уже не ответила в этом запуске: эндпоинт тот же, не долбим
                }

                try {
                    $snapshot = $service->calculate($client, $date);
                    if ($snapshot) {
                        $done++;
                        $ok[$client->id] = true;
                    } else {
                        $skipped++;
                    }
                } catch (Throwable $e) {
                    $failed[$client->id] = ['client' => $client, 'error' => $e->getMessage(), 'date' => $date->toDateString()];
                    Log::warning('client-health: CRM клиента не ответила', [
                        'client_id' => $client->id,
                        'sub_domain' => $client->sub_domain,
                        'date' => $date->toDateString(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Отчётный день: клиенты с недоступной CRM — красные с причиной. Если CRM не ответила
        // слишком многим, это общий сбой, а не проблема клиентов: красным никого не красим.
        $total = count($ok) + count($failed);
        $share = $total > 0 ? count($failed) / $total : 0;
        $marked = 0;
        if ($failed !== [] && $share <= self::MAX_FAILED_SHARE) {
            foreach ($failed as $row) {
                try {
                    $service->recordUnavailable($row['client'], $end, $row['error']) && $marked++;
                } catch (Throwable $e) {
                    Log::warning('client-health: не удалось записать недоступную CRM', ['client_id' => $row['client']->id, 'error' => $e->getMessage()]);
                }
            }
        }

        foreach ($failed as $row) {
            $this->warn("#{$row['client']->id} {$row['client']->sub_domain}: {$row['error']} (с {$row['date']}, дальше в этом запуске не запрашивали)");
        }
        if ($failed !== [] && $share > self::MAX_FAILED_SHARE) {
            $this->error(sprintf('CRM не ответила для %d%% клиентов: похоже на общий сбой, красными их не отметили.', (int) round($share * 100)));
        }

        $this->info(sprintf(
            'Посчитано: %d, пропущено (не платный и не демо): %d, CRM недоступна: %d (отмечены красными за %s: %d).',
            $done, $skipped, count($failed), $end->toDateString(), $marked
        ));

        return self::SUCCESS;
    }
}
