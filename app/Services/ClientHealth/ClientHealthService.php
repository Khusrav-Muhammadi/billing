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
            'last_activity' => $client->last_activity,
        ]);

        return $this->save($client, $date, $result + [
            'client_type' => $context['client_type'],
            'paid_until' => $context['paid_until'],
            'period_started_at' => $context['period_started_at'],
            'metrics' => $metrics,
        ]);
    }

    /**
     * CRM клиента не отвечает (тенант удалён, нет DNS, ошибка): клиент платит или в демо,
     * а работать в CRM не может. Ставим красный с понятной причиной, чтобы менеджер разобрался.
     * Вызывать только для отчётного дня и только если сбой не общий (это решает команда).
     */
    public function recordUnavailable(Client $client, Carbon $date, string $error): ?ClientHealthSnapshot
    {
        $date = $date->copy()->startOfDay();
        $context = $this->context->resolve($client, $date);
        if ($context === null) {
            return null;
        }

        $error = trim((string) preg_replace('/^CRM\s+/u', '', $error));
        $host = $client->sub_domain . '-back.' . config('services.sham.domain');
        $text = "CRM клиента недоступна ({$error}): проверьте поддомен {$host} и тенант в CRM";

        return $this->save($client, $date, [
            'client_type' => $context['client_type'],
            'logins_score' => null,
            'coverage_score' => null,
            'dialogs_score' => null,
            'entities_score' => null,
            'health_index' => 0,
            'color' => ClientHealthSnapshot::RED,
            'reasons' => [['code' => 'crm_unavailable', 'text' => $text]],
            'reason_text' => mb_substr($text, 0, 500),
            'paid_until' => $context['paid_until'],
            'period_started_at' => $context['period_started_at'],
            'metrics' => ['error' => $error],
        ]);
    }

    private function save(Client $client, Carbon $date, array $attributes): ClientHealthSnapshot
    {
        $snapshot = ClientHealthSnapshot::query()
            ->where('client_id', $client->id)
            ->whereDate('date', $date->toDateString())
            ->first() ?? new ClientHealthSnapshot(['client_id' => $client->id, 'date' => $date->toDateString()]);

        $snapshot->fill($attributes)->save();

        return $snapshot;
    }
}
