<?php

namespace App\Services\PartnerProgram;

use App\Models\Client;
use App\Models\DemoRequest;
use App\Models\Organization;
use App\Models\PartnerProgram\PartnerLead;
use App\Models\PartnerProgram\PartnerLeadAssignment;
use App\Models\User;
use App\Support\CrmHttp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Тёплые лиды партнёрской программы.
 *
 * Заявка на демо с сайта (без реферала партнёра/менеджера) -> лид в CRM партнёра (его NFR-аккаунт).
 * Очередь: сначала партнёры с большей ставкой (Золото), внутри — по кругу (кто дольше не получал лид).
 * У партнёра 24 часа: если в его CRM по лиду есть действие человека (статус, звонок, сообщение,
 * заметка, правка) — лид его, клиент закрепляется за ним. Иначе лид снимается и уходит следующему.
 * Если взять некому — лид остаётся менеджерам shamCRM (status = unassigned).
 */
class PartnerLeadService
{
    public function __construct(private readonly PartnerProgramService $program)
    {
    }

    public static function enabled(): bool
    {
        return (bool) config('partners_portal.leads.enabled');
    }

    /** Новая заявка на демо с сайта. */
    public function fromDemoRequest(DemoRequest $request): ?PartnerLead
    {
        // Клиент пришёл по ссылке партнёра или менеджера — он уже чей-то.
        if (!self::enabled() || $request->partner_id || $request->manager_id || blank($request->phone)) {
            return null;
        }

        $lead = PartnerLead::query()->firstOrCreate(
            ['demo_request_id' => $request->id],
            ['name' => (string) $request->name, 'phone' => (string) $request->phone, 'email' => $request->email]
        );

        if ($lead->wasRecentlyCreated) {
            $this->assignNext($lead);
        }

        return $lead;
    }

    /** Отдать лид следующему партнёру в очереди. */
    public function assignNext(PartnerLead $lead): ?PartnerLeadAssignment
    {
        $tried = $lead->assignments()->pluck('partner_id')->all();

        foreach ($this->queue($tried) as [$partner, $client]) {
            $assignment = $this->assignTo($lead, $partner, $client);
            if ($assignment->outcome === PartnerLeadAssignment::ACTIVE) {
                $lead->forceFill(['status' => PartnerLead::STATUS_ASSIGNED, 'partner_id' => $partner->id])->save();

                return $assignment;
            }
        }

        $lead->forceFill(['status' => PartnerLead::STATUS_UNASSIGNED, 'partner_id' => null])->save();
        Log::info('PartnerLeadService: no partner took the lead', ['partner_lead_id' => $lead->id]);

        return null;
    }

    /** Проверка SLA: принятые закрепляем, просроченные передаём дальше. Запускается по расписанию. */
    public function checkActive(): array
    {
        $stats = ['accepted' => 0, 'expired' => 0, 'errors' => 0];

        PartnerLeadAssignment::query()
            ->where('outcome', PartnerLeadAssignment::ACTIVE)
            ->with('lead')
            ->orderBy('id')
            ->chunkById(100, function (Collection $assignments) use (&$stats) {
                foreach ($assignments as $assignment) {
                    try {
                        $result = $this->checkOne($assignment);
                        if ($result) {
                            $stats[$result]++;
                        }
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::warning('PartnerLeadService: SLA check failed', [
                            'assignment_id' => $assignment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $stats;
    }

    /** @return 'accepted'|'expired'|null */
    public function checkOne(PartnerLeadAssignment $assignment): ?string
    {
        $client = Client::query()->find($assignment->client_id);
        if (!$client || !$assignment->crm_lead_id) {
            return $this->expire($assignment, 'CRM партнёра недоступна');
        }

        $response = CrmHttp::client()->timeout(15)->post(
            $this->crmUrl($client, '/api/partner-leads/' . $assignment->crm_lead_id . '/activity'),
            ['since' => $assignment->assigned_at->toIso8601String()]
        );

        if ($response->successful() && $response->json('result.active')) {
            $this->accept($assignment, (array) $response->json('result.kinds', []));

            return 'accepted';
        }

        if ($assignment->deadline_at->isPast()) {
            // CRM не ответила, а срок вышел — тоже передаём дальше: лид не должен зависать.
            return $this->expire($assignment, $response->successful() ? null : 'CRM: HTTP ' . $response->status());
        }

        return null;
    }

    /** Лиды партнёра для кабинета. */
    public function forPartner(User $partner, int $limit = 100): Collection
    {
        return PartnerLeadAssignment::query()
            ->where('partner_id', $partner->id)
            ->where('outcome', '!=', PartnerLeadAssignment::FAILED)
            ->with('lead')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (PartnerLeadAssignment $a) => [
                'id' => $a->id,
                'name' => $a->lead?->name,
                // Контакты — только пока лид у партнёра или уже его.
                'phone' => $a->outcome === PartnerLeadAssignment::EXPIRED ? null : $a->lead?->phone,
                'email' => $a->outcome === PartnerLeadAssignment::EXPIRED ? null : $a->lead?->email,
                'outcome' => $a->outcome,
                'assigned_at' => $a->assigned_at?->toIso8601String(),
                'deadline_at' => $a->deadline_at?->toIso8601String(),
                'resolved_at' => $a->resolved_at?->toIso8601String(),
                'activity' => $a->activity ?? [],
            ]);
    }

    /**
     * Очередь партнёров: одобренные, с привязанной CRM (NFR, доступ открыт), кроме своей компании
     * и тех, кому этот лид уже давали. Сначала большая ставка (Золото), потом кто дольше без лида.
     *
     * @return list<array{0: User, 1: Client}>
     */
    private function queue(array $exclude): array
    {
        $excluded = array_merge($exclude, (array) config('partners_portal.leads.excluded_partner_ids', []));

        $partners = User::query()
            ->where('role', 'partner')
            ->where('approval_status', PartnerProgramService::STATUS_APPROVED)
            ->whereNotNull('demo_client_id')
            ->when($excluded, fn ($q) => $q->whereNotIn('id', $excluded))
            ->get();

        $lastAssigned = PartnerLeadAssignment::query()
            ->whereIn('partner_id', $partners->pluck('id'))
            ->selectRaw('partner_id, max(assigned_at) as last_at')
            ->groupBy('partner_id')
            ->pluck('last_at', 'partner_id');

        $rows = [];
        foreach ($partners as $partner) {
            $client = Client::query()->find($partner->demo_client_id);
            $organization = $client ? Organization::query()->where('client_id', $client->id)->orderBy('id')->first() : null;
            if (!$client || !$client->nfr || !$client->sub_domain || !$organization?->has_access) {
                continue;
            }
            $rows[] = [
                'partner' => $partner,
                'client' => $client,
                'percent' => $this->program->currentPercent($partner)['tariff'],
                'last' => (string) ($lastAssigned[$partner->id] ?? ''),
            ];
        }

        usort($rows, fn ($a, $b) => [$b['percent'], $a['last'], $a['partner']->id] <=> [$a['percent'], $b['last'], $b['partner']->id]);

        return array_map(fn ($r) => [$r['partner'], $r['client']], $rows);
    }

    private function assignTo(PartnerLead $lead, User $partner, Client $client): PartnerLeadAssignment
    {
        $now = now();
        $assignment = PartnerLeadAssignment::query()->create([
            'partner_lead_id' => $lead->id,
            'partner_id' => $partner->id,
            'client_id' => $client->id,
            'assigned_at' => $now,
            'deadline_at' => $now->copy()->addHours((int) config('partners_portal.leads.sla_hours', 24)),
        ]);

        try {
            $response = CrmHttp::client()->timeout(20)->post($this->crmUrl($client, '/api/partner-leads'), [
                'ref' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'email' => $lead->email,
            ]);

            $crmLeadId = (int) $response->json('result.lead_id');
            if (!$response->successful() || $crmLeadId <= 0) {
                throw new RuntimeException('HTTP ' . $response->status());
            }

            $assignment->forceFill([
                'crm_lead_id' => $crmLeadId,
                'crm_created' => (bool) $response->json('result.created', true),
            ])->save();
        } catch (\Throwable $e) {
            $assignment->forceFill([
                'outcome' => PartnerLeadAssignment::FAILED,
                'resolved_at' => now(),
                'error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();
            Log::warning('PartnerLeadService: failed to create lead in partner CRM', [
                'partner_lead_id' => $lead->id,
                'partner_id' => $partner->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $assignment;
    }

    private function accept(PartnerLeadAssignment $assignment, array $kinds): void
    {
        DB::transaction(function () use ($assignment, $kinds) {
            $assignment->forceFill([
                'outcome' => PartnerLeadAssignment::ACCEPTED,
                'resolved_at' => now(),
                'activity' => $kinds,
            ])->save();

            $lead = $assignment->lead;
            $lead->forceFill([
                'status' => PartnerLead::STATUS_ACCEPTED,
                'partner_id' => $assignment->partner_id,
                'accepted_at' => now(),
            ])->save();

            // Клиент (его демо-аккаунт) закрепляется за партнёром — с оплат партнёр получает комиссию.
            $clientId = $lead->demoRequest?->client_id;
            if ($clientId) {
                Client::query()->whereKey($clientId)->whereNull('partner_id')->update(['partner_id' => $assignment->partner_id]);
            }
            $lead->demoRequest?->forceFill(['partner_id' => $assignment->partner_id])->save();
        });
    }

    private function expire(PartnerLeadAssignment $assignment, ?string $error = null): string
    {
        $client = Client::query()->find($assignment->client_id);
        if ($client && $assignment->crm_lead_id) {
            try {
                CrmHttp::client()->timeout(15)->post(
                    $this->crmUrl($client, '/api/partner-leads/' . $assignment->crm_lead_id . '/revoke'),
                    ['created' => $assignment->crm_created]
                );
            } catch (\Throwable $e) {
                Log::warning('PartnerLeadService: revoke failed', ['assignment_id' => $assignment->id, 'error' => $e->getMessage()]);
            }
        }

        $assignment->forceFill([
            'outcome' => PartnerLeadAssignment::EXPIRED,
            'resolved_at' => now(),
            'error' => $error,
        ])->save();

        $this->assignNext($assignment->lead);

        return 'expired';
    }

    private function crmUrl(Client $client, string $path): string
    {
        return CrmHttp::tenantUrl((string) $client->sub_domain, (string) config('services.sham.domain'), $path);
    }
}
