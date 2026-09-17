<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Jobs\ProvisionDemoAccountJob;
use App\Models\DemoRequest;
use App\Services\Site\DemoEmailAvailability;
use App\Services\Site\TurnstileVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoRequestController extends Controller
{
    private const POLL_INTERVAL_MS = 2000;

    public function emailCheck(Request $request, DemoEmailAvailability $availability): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($availability->check($data['email']));
    }

    public function store(
        Request $request,
        DemoEmailAvailability $availability,
        TurnstileVerifier $turnstile
    ): JsonResponse {
        $data = $this->validateRequest($request);
        $data['email'] = DemoEmailAvailability::normalize($data['email']);

        if (!$turnstile->passes($data['cf_turnstile_response'] ?? null, $request->ip())) {
            return response()->json([
                'message' => 'Не удалось подтвердить, что вы не робот. Обновите страницу и попробуйте снова.',
                'errors' => ['cf_turnstile_response' => ['Проверка не пройдена.']],
            ], 422);
        }

        if ($existing = $this->findReusable($data['email'])) {
            return response()->json($this->payload($existing), $existing->isPending() ? 202 : 200);
        }

        $check = $availability->check($data['email']);

        if (!$check['available']) {
            return response()->json([
                'message' => $check['message'],
                'reason' => $check['reason'],
                'errors' => ['email' => [$check['message']]],
            ], 422);
        }

        $demoRequest = DemoRequest::create([
            'name' => $data['fio'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'country_id' => $data['region_id'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'status' => DemoRequest::STATUS_QUEUED,
            'step' => DemoRequest::STEP_ACCOUNT,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        ProvisionDemoAccountJob::dispatch($demoRequest);

        return response()->json($this->payload($demoRequest), 202);
    }

    public function show(string $uuid): JsonResponse
    {
        $demoRequest = DemoRequest::query()->where('uuid', $uuid)->first();

        if (!$demoRequest) {
            return response()->json([
                'message' => 'Заявка не найдена. Заполните форму ещё раз.',
            ], 404);
        }

        $this->failIfStale($demoRequest);

        return response()->json($this->payload($demoRequest));
    }


    private function failIfStale(DemoRequest $demoRequest): void
    {
        if (!$demoRequest->isPending()) {
            return;
        }

        $limit = (int) config('demo.provisioning.stale_after_minutes', 15);

        if ($demoRequest->updated_at?->diffInMinutes(now()) < $limit) {
            return;
        }

        $demoRequest->markFailed(
            'timeout',
            'Подготовка демо затянулась. Напишите в поддержку — выдадим доступ вручную.'
        );
    }


    private function findReusable(string $email): ?DemoRequest
    {
        $demoRequest = DemoRequest::query()
            ->where('email', $email)
            ->whereIn('status', [
                DemoRequest::STATUS_QUEUED,
                DemoRequest::STATUS_PROVISIONING,
                DemoRequest::STATUS_READY,
            ])
            ->latest('id')
            ->first();

        if (!$demoRequest) {
            return null;
        }

        if ($demoRequest->isPending()) {
            $this->failIfStale($demoRequest);

            return $demoRequest->isPending() ? $demoRequest : null;
        }

        return $demoRequest->hasUsableLoginUrl() ? $demoRequest : null;
    }

    private function payload(DemoRequest $demoRequest): array
    {
        return [
            'uuid' => $demoRequest->uuid,
            'status' => $demoRequest->status,
            'step' => $demoRequest->step,
            'step_number' => $demoRequest->stepNumber(),
            'step_total' => count(DemoRequest::STEPS),
            'login_url' => $demoRequest->hasUsableLoginUrl() ? $demoRequest->login_url : null,
            'failure_code' => $demoRequest->failure_code,
            'message' => $demoRequest->failure_reason,
            'poll_interval_ms' => self::POLL_INTERVAL_MS,
        ];
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'fio' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'region_id' => ['nullable', 'integer'],
            'partner_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'cf_turnstile_response' => ['nullable', 'string', 'max:2048'],
        ], [
            'fio.required' => 'Укажите имя или название компании.',
            'phone.required' => 'Укажите телефон.',
            'email.required' => 'Укажите email — на него придут доступы.',
            'email.email' => 'Проверьте адрес почты.',
        ]);
    }
}
