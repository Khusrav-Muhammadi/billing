<?php

namespace App\Services\PartnerProgram;

use App\Jobs\SendPartnerDemoLinkCodeJob;
use App\Models\Client;
use App\Models\DemoRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\Site\DemoEmailAvailability;
use App\Support\CrmHttp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Своё демо партнёра в кабинете partners.shamcrm.com.
 *
 * - Создал демо из кабинета на свою почту — привязывается само (users.demo_client_id).
 * - Уже есть демо — партнёр вводит его почту, мы шлём на неё код, после кода привязываем.
 * - «Войти в CRM» — каждый раз свежая одноразовая ссылка из CRM (только для демо-организаций).
 */
class PartnerOwnDemoService
{
    private const CODE_TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    /** Текущее демо партнёра или null. Подхватывает демо, созданное из кабинета на почту партнёра. */
    public function current(User $partner): ?array
    {
        $client = $this->linkedClient($partner) ?? $this->autoLinkCreated($partner);

        return $client ? $this->serialize($client) : null;
    }

    /**
     * Шаг 1 привязки: находим демо по почте и отправляем на неё код.
     *
     * @throws ValidationException
     */
    public function startLink(User $partner, string $email): array
    {
        // Не больше 5 писем с кодом за 15 минут на партнёра.
        $this->hit('link', $partner, 5, self::CODE_TTL_MINUTES * 60, 'email');

        $email = DemoEmailAvailability::normalize($email);

        $client = Client::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            // Демо или уже рабочая NFR партнёра (после одобрения is_demo может быть снят, а nfr = 1).
            ->where(fn ($q) => $q->where('is_demo', true)->orWhere('nfr', true))
            ->latest('id')
            ->first();

        if (!$client || !$this->organization($client)) {
            throw ValidationException::withMessages(['email' => 'Демо shamCRM с такой почтой не найдено. Проверьте почту или создайте новое демо.']);
        }

        $this->assertAvailableFor($partner, $client);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->codeKey($partner), [
            'client_id' => $client->id,
            'hash' => hash('sha256', $code),
            'attempts' => 0,
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        SendPartnerDemoLinkCodeJob::dispatch($email, $code, (string) $partner->name, $this->crmHost($client), self::CODE_TTL_MINUTES);

        return ['email' => $email, 'ttl_minutes' => self::CODE_TTL_MINUTES];
    }

    /**
     * Шаг 2 привязки: проверяем код.
     *
     * @throws ValidationException
     */
    public function confirmLink(User $partner, string $code): array
    {
        $key = $this->codeKey($partner);
        $pending = Cache::get($key);

        if (!$pending) {
            throw ValidationException::withMessages(['code' => 'Код истёк. Запросите новый.']);
        }

        if (!hash_equals($pending['hash'], hash('sha256', trim($code)))) {
            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);
                throw ValidationException::withMessages(['code' => 'Слишком много попыток. Запросите новый код.']);
            }
            Cache::put($key, $pending, now()->addMinutes(self::CODE_TTL_MINUTES));
            throw ValidationException::withMessages(['code' => 'Неверный код.']);
        }

        Cache::forget($key);
        $client = Client::query()->findOrFail($pending['client_id']);
        $this->assertAvailableFor($partner, $client);
        $this->link($partner, $client);

        return $this->serialize($client);
    }

    /** Одноразовая ссылка входа в демо партнёра. */
    public function loginUrl(User $partner): string
    {
        // Не больше 20 входов в минуту на партнёра.
        if (RateLimiter::tooManyAttempts($this->limiterKey('login', $partner), 20)) {
            throw new RuntimeException('Too many demo login attempts.');
        }
        RateLimiter::hit($this->limiterKey('login', $partner), 60);

        $client = $this->linkedClient($partner);
        $organization = $client ? $this->organization($client) : null;

        if (!$client || !$organization) {
            throw new RuntimeException('Partner has no linked demo.');
        }

        $response = CrmHttp::client()
            ->timeout(15)
            ->post(
                CrmHttp::tenantUrl((string) $client->sub_domain, (string) config('services.sham.domain'), '/api/organization/demo-login-token'),
                // own_crm: привязанная CRM уже рабочая (NFR, не демо) — CRM иначе отвечает 404.
                ['b_organization_id' => $organization->id, 'own_crm' => (bool) $client->nfr]
            );

        $token = $response->json('result.demo_login_token');

        if (!$response->successful() || !is_string($token) || $token === '') {
            Log::warning('PartnerOwnDemoService: CRM did not issue demo login token', [
                'partner_id' => $partner->id,
                'client_id' => $client->id,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('CRM did not issue demo login token.');
        }

        return rtrim($client->crmUrl(), '/') . '/demo-login?token=' . urlencode($token);
    }

    private function linkedClient(User $partner): ?Client
    {
        return $partner->demo_client_id ? Client::query()->find($partner->demo_client_id) : null;
    }

    /** Демо, которое партнёр создал из кабинета на свою почту. */
    private function autoLinkCreated(User $partner): ?Client
    {
        $request = DemoRequest::query()
            ->where('partner_id', $partner->id)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $partner->email)])
            ->whereNotNull('client_id')
            ->latest('id')
            ->first();

        $client = $request ? Client::query()->find($request->client_id) : null;
        if (!$client) {
            return null;
        }

        $this->link($partner, $client);

        return $client;
    }

    private function link(User $partner, Client $client): void
    {
        $partner->forceFill(['demo_client_id' => $client->id])->save();

        if (!$client->partner_id) {
            $client->forceFill(['partner_id' => $partner->id])->save();
        }

        // У одобренного партнёра демо = его рабочая CRM (NFR).
        if (($partner->approval_status ?? PartnerProgramService::STATUS_APPROVED) === PartnerProgramService::STATUS_APPROVED && !$client->nfr) {
            $client->forceFill(['nfr' => true])->save();
        }
    }

    /** @throws ValidationException */
    private function assertAvailableFor(User $partner, Client $client): void
    {
        $takenByOther = ($client->partner_id && (int) $client->partner_id !== (int) $partner->id)
            || User::query()->where('demo_client_id', $client->id)->where('id', '!=', $partner->id)->exists();

        if ($takenByOther) {
            throw ValidationException::withMessages(['email' => 'Это демо закреплено за другим партнёром. Напишите менеджеру.']);
        }
    }

    private function organization(Client $client): ?Organization
    {
        return Organization::query()->where('client_id', $client->id)->orderBy('id')->first();
    }

    private function serialize(Client $client): array
    {
        $request = DemoRequest::query()->where('client_id', $client->id)->latest('id')->first();
        $pending = $request && $request->isPending();

        return [
            'client_id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'crm_host' => $this->crmHost($client),
            'crm_url' => $client->sub_domain ? $client->crmUrl() : null,
            'status' => $pending ? $request->status : ($request?->status === DemoRequest::STATUS_FAILED ? 'failed' : 'ready'),
            'step_number' => $pending ? $request->stepNumber() : null,
            'step_total' => count(DemoRequest::STEPS),
            'message' => $request?->status === DemoRequest::STATUS_FAILED ? $request->failure_reason : null,
            'is_active' => (bool) ($this->organization($client)?->has_access ?? false),
        ];
    }

    private function crmHost(Client $client): string
    {
        return $client->sub_domain ? rtrim(preg_replace('#^https?://#', '', $client->crmUrl()), '/') : '';
    }

    /** @throws ValidationException */
    private function hit(string $action, User $partner, int $max, int $decaySeconds, string $field): void
    {
        $key = $this->limiterKey($action, $partner);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
            throw ValidationException::withMessages([$field => "Слишком много попыток. Попробуйте через {$minutes} мин."]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function limiterKey(string $action, User $partner): string
    {
        return 'partner_own_demo_' . $action . '_' . $partner->id;
    }

    private function codeKey(User $partner): string
    {
        return 'partner_demo_link_code_' . $partner->id;
    }
}
