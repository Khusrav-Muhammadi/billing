<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SendPartnerPortalAccessJob;
use App\Models\User;
use App\Services\PartnerProgram\PartnerDemoService;
use App\Services\PartnerProgram\PartnerOwnDemoService;
use App\Services\PartnerProgram\PartnerProgramService;
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

        $partners = User::query()
            ->where('role', 'partner')
            ->when($status, fn ($q) => $q->where('approval_status', $status))
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $u) => $this->service->serializePartner($u))
            ->values();

        return response()->json(['partners' => $partners]);
    }

    public function show(User $partner): JsonResponse
    {
        abort_unless($partner->role === 'partner', 404);

        return response()->json(['partner' => $this->service->serializePartner($partner)]);
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
