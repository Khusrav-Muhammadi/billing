<?php

namespace App\Services\ClientHealth;

use App\Models\Client;
use App\Models\ClientHealthSnapshot;
use Illuminate\Support\Carbon;

/**
 * Индекс здоровья одного клиента за один день: контекст биллинга + цифры CRM -> строка истории.
 */
class ClientHealthService
{
    public function __construct(
        private readonly ClientHealthContextService $context,
        private readonly ClientHealthCrmFetchService $crm,
        private readonly ClientHealthCalculator $calculator,
    ) {
    }

    /**
     * @return ClientHealthSnapshot|null null — клиента в этот день не считаем (не платный и не демо)
     * @throws \RuntimeException если CRM не отдала цифры
     */
    public function calculate(Client $client, Carbon $date): ?ClientHealthSnapshot
    {
        $date = $date->copy()->startOfDay();
        $context = $this->context->resolve($client, $date);
        if ($context === null) {
            return null;
        }

        $metrics = $this->crm->fetch($client, $date, $context['organization_id']);

        $result = $this->calculator->calculate($metrics, [
            'date' => $date->toDateString(),
            'client_type' => $context['client_type'],
            'paid_until' => $context['paid_until'],
            'period_started_at' => $context['period_started_at'],
            'previous_index' => $this->context->previousIndex($client, $date),
        ]);

        $snapshot = ClientHealthSnapshot::query()
            ->where('client_id', $client->id)
            ->whereDate('date', $date->toDateString())
            ->first() ?? new ClientHealthSnapshot(['client_id' => $client->id, 'date' => $date->toDateString()]);

        $snapshot->fill($result + [
            'client_type' => $context['client_type'],
            'paid_until' => $context['paid_until'],
            'period_started_at' => $context['period_started_at'],
            'metrics' => $metrics,
        ])->save();

        return $snapshot;
    }
}
