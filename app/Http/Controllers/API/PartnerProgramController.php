<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SendPartnerPortalAccessJob;
use App\Models\User;
use App\Services\PartnerProgram\PartnerDemoService;
use App\Services\PartnerProgram\PartnerOwnDemoService;
use App\Services\PartnerProgram\PartnerProgramService;
use App\Services\PartnerProgram\PartnerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API для партнёрского портала partners.shamcrm.com (middleware partners.token).
 */
class PartnerProgramController extends Controller
{
    public function __construct(private readonly PartnerProgramService $service)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('users', 'login')],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
            // Портал сгенерировал пароль сам: отправляем его партнёру на почту.
            'send_access_email' => ['nullable', 'boolean'],
        ]);

        $user = $this->service->register($data);

        if (!empty($data['send_access_email'])) {
            SendPartnerPortalAccessJob::dispatch($user, $data['password']);
        }

        return response()->json(['partner' => $this->service->serializePartner($user)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('approval_status');

        // Активный партнёр в админке портала = есть хотя бы один клиент с действующим подключением.
        $activeClients = \App\Models\Organization::query()
            ->whereHas('latestConnection', fn ($q) => $q->where('status', 'connected'))
            ->join('clients', 'clients.id', '=', 'organizations.client_id')
            ->whereNotNull('clients.partner_id')
            ->groupBy('clients.partner_id')
            ->selectRaw('clients.partner_id AS pid, COUNT(DISTINCT organizations.client_id) AS c')
            ->pluck('c', 'pid');

        $partners = User::query()
            ->with('currency:id,symbol_code')
            ->where('role', 'partner')
            ->when($status, fn ($q) => $q->where('approval_status', $status))
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $u) => $this->service->serializePartner($u) + ['active_clients' => (int) ($activeClients[$u->id] ?? 0)])
            ->values();

        return response()->json(['partners' => $partners]);
    }

    /**
     * Отчёт по партнёрам за период (по умолчанию текущий месяц): подключения, продления, оборот, комиссия,
     * светофор партнёров, светофор их клиентов, NFR. Для рабочего стола и раздела «Партнёры» в админке портала.
     */
    public function report(Request $request, PartnerReportService $report): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? \Illuminate\Support\Carbon::parse($data['from']) : now()->startOfMonth();
        $to = isset($data['to']) ? \Illuminate\Support\Carbon::parse($data['to']) : now();

        return response()->json($report->report($from->startOfDay(), $to->endOfDay()));
    }

    /**
     * Админ портала закрепляет клиента без партнёра за партнёром («Отправить партнёру» в карточке организации).
     * Чужого клиента не переназначаем: только если партнёра у клиента нет.
     */
    public function assignOrganizationPartner(Request $request, \App\Models\Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'partner_id' => ['required', 'integer'],
        ]);

        $partner = User::query()->whereKey($data['partner_id'])->where('role', 'partner')->first();
        if (!$partner || ($partner->approval_status ?? PartnerProgramService::STATUS_APPROVED) !== PartnerProgramService::STATUS_APPROVED) {
            return response()->json(['message' => 'Партнёр не найден или ещё не одобрен.'], 422);
        }

        $client = $organization->client;
        if (!$client) {
            return response()->json(['message' => 'У организации нет клиента.'], 422);
        }
        if ($client->partner_id !== null) {
            return response()->json(['message' => 'У этого клиента уже есть партнёр.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($organization, $client, $partner) {
            $client->update(['partner_id' => $partner->id]);

            // Ночная команда снимает партнёра у демо старше 30 дней без подключения (один раз на организацию).
            // Закрепление админом — осознанное решение: такую старую организацию отмечаем, чтобы его не сняли этой же ночью.
            if ($organization->partner_auto_cleared_at === null
                && $organization->created_at?->lte(now()->subDays(30))
                && !$organization->connections()->exists()) {
                $organization->forceFill(['partner_auto_cleared_at' => now()])->save();
            }
        });

        return response()->json(['success' => true, 'partner' => ['id' => $partner->id, 'name' => $partner->name]]);
    }

    public function show(User $partner): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        return response()->json([
            'partner' => $this->service->serializePartner($partner) + $this->service->partnerProfile($partner),
        ]);
    }

    public function approve(Request $request, User $partner): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);
        $authorId = $request->integer('author_id') ?: null;

        return response()->json(['partner' => $this->service->serializePartner($this->service->approve($partner, $authorId))]);
    }

    public function reject(User $partner): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        return response()->json(['partner' => $this->service->serializePartner($this->service->reject($partner))]);
    }

    public function setPercent(Request $request, User $partner): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);
        $data = $request->validate([
            'procent_from_tariff' => ['required', 'integer', 'min:0', 'max:100'],
            'procent_from_pack' => ['required', 'integer', 'min:0', 'max:100'],
            'date' => ['nullable', 'date'],
        ]);

        $this->service->setPercent($partner, $data['procent_from_tariff'], $data['procent_from_pack'], $data['date'] ?? null);

        return response()->json(['partner' => $this->service->serializePartner($partner->refresh())]);
    }

    /** Валюта договора партнёра (портал присылает при подтверждении договора). */
    public function setCurrency(Request $request, User $partner): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);
        $data = $request->validate([
            'currency' => ['required', 'string', 'in:UZS,USD,TJS'],
        ]);

        $this->service->setCurrency($partner, $data['currency']);

        return response()->json(['partner' => $this->service->serializePartner($partner->refresh())]);
    }

    /** Демо shamCRM: своё (на почту партнёра) или для клиента партнёра. */
    public function storeDemo(Request $request, User $partner, PartnerDemoService $demos): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
        ]);

        $demoRequest = $demos->create($partner, $data);

        return response()->json(['demo' => $demos->serialize($demoRequest)], $demoRequest->isPending() ? 202 : 200);
    }

    /** Своё демо партнёра (привязанное) или null. */
    public function ownDemo(User $partner, PartnerOwnDemoService $own): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        return response()->json(['demo' => $own->current($partner)]);
    }

    /** Привязать существующее демо: шаг 1, код на почту демо. */
    public function ownDemoLink(Request $request, User $partner, PartnerOwnDemoService $own): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return response()->json($own->startLink($partner, $data['email']));
    }

    /** Привязать существующее демо: шаг 2, проверка кода. */
    public function ownDemoConfirm(Request $request, User $partner, PartnerOwnDemoService $own): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        return response()->json(['demo' => $own->confirmLink($partner, $data['code'])]);
    }

    /** Одноразовая ссылка входа в своё демо. */
    public function ownDemoLogin(User $partner, PartnerOwnDemoService $own): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        try {
            return response()->json(['url' => $own->loginUrl($partner)]);
        } catch (\RuntimeException $e) {
            // 503, не 502: Cloudflare подменяет 502 от origin своей страницей и теряет наш ответ.
            return response()->json(['message' => 'Не удалось открыть демо. Попробуйте через минуту.'], 503);
        }
    }

    /** Тёплые лиды партнёра. */
    public function leads(User $partner, \App\Services\PartnerProgram\PartnerLeadService $leads): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        return response()->json([
            'enabled' => \App\Services\PartnerProgram\PartnerLeadService::enabled(),
            'sla_hours' => (int) config('partners_portal.leads.sla_hours', 24),
            'has_crm' => (bool) $partner->demo_client_id,
            'leads' => $leads->forPartner($partner)->values(),
        ]);
    }

    public function demos(User $partner, PartnerDemoService $demos): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        return response()->json(['demos' => $demos->list($partner)->values()]);
    }

    public function stats(Request $request, User $partner): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);
        $since = $request->query('since');

        return response()->json($this->service->stats($partner, is_string($since) && $since !== '' ? $since : null));
    }
}
