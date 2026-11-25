<?php

namespace App\Services\ClientHealth;

use App\Models\Client;
use App\Models\ClientHealthSnapshot;
use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use App\Services\Organizations\OrganizationValidityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Что биллинг знает о клиенте на отчётный день: платный он или демо, «оплачено до»,
 * начало текущего оплаченного периода (для серого цвета), индекс 2 недели назад.
 *
 * Клиент — тенант CRM (clients.sub_domain). У клиента бывает несколько организаций:
 *  - платный, если хотя бы одна организация на конец дня подключена (последний статус connected);
 *  - демо, если подключений не было ни у одной организации и демо ещё идёт (14 дней с создания);
 *  - NFR (рабочая CRM партнёра), отключённые и истёкшие демо не считаются.
 */
class ClientHealthContextService
{
    public const DEMO_DAYS = 14;

    public function __construct(private readonly OrganizationValidityService $validity)
    {
    }

    /** Клиенты-кандидаты; точный тип определяет resolve(). */
    public function candidates(): Builder
    {
        return Client::query()
            ->where(fn ($q) => $q->where('nfr', false)->orWhereNull('nfr'))
            ->whereNotNull('sub_domain')
            ->where('sub_domain', '!=', '')
            ->whereHas('organizations');
    }

    /**
     * @return array{client_type: string, paid_until: ?string, period_started_at: ?string, organization_id: ?int}|null
     *         null — клиента в этот день не считаем.
     */
    public function resolve(Client $client, Carbon $date): ?array
    {
        $dayEnd = $date->copy()->endOfDay();
        $organizations = $client->organizations()->with('client.country')->get();

        $connected = $organizations->filter(fn (Organization $o) => $this->latestStatus($o, $dayEnd) === 'connected')->values();

        if ($connected->isNotEmpty()) {
            $validUntil = $connected
                ->map(fn (Organization $o) => $this->validity->calculateValidUntil($o, $date->copy()))
                ->filter()
                ->sort()
                ->first();

            $periodStart = $connected
                ->map(fn (Organization $o) => $this->periodStart($o, $dayEnd))
                ->filter()
                ->sort()
                ->first();

            return [
                'client_type' => 'paid',
                'paid_until' => $validUntil?->toDateString(),
                'period_started_at' => $periodStart?->toDateString(),
                'organization_id' => (int) $connected->first()->id,
            ];
        }

        $everConnected = OrganizationConnectionStatus::query()
            ->whereIn('organization_id', $organizations->pluck('id'))
            ->where('status_date', '<=', $dayEnd)
            ->exists();
        if ($everConnected) {
            return null; // был платным и отключён
        }

        $demo = $organizations
            ->filter(fn (Organization $o) => $o->created_at
                && $o->created_at->lte($dayEnd)
                && $o->created_at->copy()->addDays(self::DEMO_DAYS)->gt($dayEnd))
            ->sortByDesc('created_at')
            ->first();

        if (!$demo) {
            return null;
        }

        return [
            'client_type' => 'demo',
            'paid_until' => $demo->created_at->copy()->addDays(self::DEMO_DAYS)->toDateString(),
            'period_started_at' => $demo->created_at->toDateString(),
            'organization_id' => (int) $demo->id,
        ];
    }

    public function previousIndex(Client $client, Carbon $date): ?int
    {
        $value = ClientHealthSnapshot::query()
            ->where('client_id', $client->id)
            ->whereDate('date', $date->copy()->subDays(ClientHealthCalculator::DROP_DAYS)->toDateString())
            ->value('health_index');

        return $value === null ? null : (int) $value;
    }

    private function latestStatus(Organization $organization, Carbon $dayEnd): ?string
    {
        return OrganizationConnectionStatus::query()
            ->where('organization_id', $organization->id)
            ->where('status_date', '<=', $dayEnd)
            ->orderByDesc('status_date')
            ->orderByDesc('updated_at')
            ->value('status');
    }

    /**
     * Начало текущего оплаченного периода: первое подключение после последнего отключения.
     * Повторная оплата после отключения снова даёт 28 серых дней.
     */
    private function periodStart(Organization $organization, Carbon $dayEnd): ?Carbon
    {
        /** @var Collection<int, OrganizationConnectionStatus> $rows */
        $rows = OrganizationConnectionStatus::query()
            ->where('organization_id', $organization->id)
            ->where('status_date', '<=', $dayEnd)
            ->orderBy('status_date')
            ->orderBy('id')
            ->get(['status', 'status_date']);

        $start = null;
        foreach ($rows as $row) {
            if ($row->status === 'disconnected') {
                $start = null;
            } elseif ($row->status === 'connected' && $start === null) {
                $start = Carbon::parse($row->status_date);
            }
        }

        return $start;
    }
}
