<?php

namespace App\Services\Site;

use App\Exceptions\DemoProvisioningException;
use App\Jobs\SendSiteAccessEmailJob;
use App\Jobs\SendToShamJob;
use App\Models\Client;
use App\Models\Country;
use App\Models\DemoRequest;
use App\Models\Organization;
use App\Models\Tariff;
use App\Models\User;
use App\Services\IntegrationActionLogService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Выдача демо-аккаунта по заявке с сайта.
 *
 * Шаги идут по порядку и отмечаются в самой заявке, чтобы сайт мог показать
 * честный прогресс. Каждый шаг идемпотентен: повторный запуск джобы
 * дописывает недостающее, а не создаёт дубликаты.
 */
class DemoProvisioner
{
    public function __construct(
        private IntegrationActionLogService $logs,
        private DemoSubdomainGenerator $subdomains,
    ) {
    }

    public function provision(DemoRequest $request): void
    {
        if (!$request->isPending()) {
            return;
        }

        try {
            $client = $this->resolveClient($request);
            $this->ensureSubdomain($request, $client);

            $organization = $this->resolveOrganization($request, $client);
            $password = Str::random(12);
            $loginToken = $this->createOrganizationInCrm($request, $client, $organization, $password);

            // Сначала отдаём доступ: с этого момента сайт уже может открыть
            // кабинет, и ничто дальше не должно этому помешать.
            $base = rtrim($client->crmUrl(), '/');

            $request->markReady(
                $loginToken === null
                    ? $base . '/login'
                    : $base . '/demo-login?token=' . urlencode($loginToken),
                (int) config('demo.login_token_ttl_minutes', 15)
            );

            // Письмо и уведомление менеджеру — уже не критичный путь. Их сбой
            // не должен приводить к откату живого кабинета.
            try {
                $this->notify($client, $organization, $password);
            } catch (\Throwable $e) {
                Log::error('DemoProvisioner: notifications failed', [
                    'demo_request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (DemoProvisioningException $e) {
            // Свежий поддомен может быть ещё не проксирован — такие сбои
            // лечит повтор джобы, а не сообщение об ошибке пользователю.
            if ($e->retryable) {
                throw $e;
            }

            $this->fail($request, $e->reason, $e->getMessage(), $e->rollback);
        } catch (\Throwable $e) {
            Log::error('DemoProvisioner: unexpected failure', [
                'demo_request_id' => $request->id,
                'email' => $request->email,
                'step' => $request->step,
                'error' => $e->getMessage(),
            ]);

            $this->fail(
                $request,
                'unexpected',
                'Не удалось подготовить демо. Попробуйте ещё раз или напишите в поддержку.',
                rollback: true
            );
        }
    }

    /** Финальный отказ: вызывается и из provision, и из failed() джобы. */
    public function fail(DemoRequest $request, string $code, string $message, bool $rollback): void
    {
        if ($rollback) {
            $this->rollback($request);
        }

        $request->markFailed($code, $message);
    }

    /* ---------------------------------------------------------------- */
    /* Шаг 1. Клиент в биллинге                                          */
    /* ---------------------------------------------------------------- */

    private function resolveClient(DemoRequest $request): Client
    {
        $request->markStep(DemoRequest::STEP_ACCOUNT);

        if ($request->client_id && $client = Client::query()->find($request->client_id)) {
            return $client;
        }

        $subdomain = $this->subdomains->generateUnique($request->email);

        $attributes = [
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'country_id' => $request->country_id ?: 1,
            'is_demo' => true,
            'sub_domain' => $subdomain,
            'manager_id' => $request->manager_id,
            'partner_id' => $this->resolvePartnerId($request),
        ];

        try {
            $client = Client::create($attributes);
        } catch (\Throwable $e) {
            Log::error('DemoProvisioner: failed to create client', [
                'demo_request_id' => $request->id,
                'attributes' => $attributes,
                'error' => $e->getMessage(),
            ]);

            throw new DemoProvisioningException(
                'client_create_failed',
                'Не удалось создать аккаунт. Попробуйте ещё раз через минуту.',
                retryable: true
            );
        }

        $request->update([
            'client_id' => $client->id,
            'sub_domain' => $client->sub_domain,
        ]);

        return $client;
    }

    /** Партнёр менеджера важнее партнёра из ссылки: менеджер закреплён явно. */
    private function resolvePartnerId(DemoRequest $request): ?int
    {
        if ($request->manager_id) {
            $manager = User::query()->find($request->manager_id);

            if ($manager?->partner_id) {
                return (int) $manager->partner_id;
            }
        }

        return $request->partner_id ? (int) $request->partner_id : null;
    }

    /* ---------------------------------------------------------------- */
    /* Шаг 2. Поддомен и база тенанта                                    */
    /* ---------------------------------------------------------------- */

    private function ensureSubdomain(DemoRequest $request, Client $client): void
    {
        $request->markStep(DemoRequest::STEP_WORKSPACE);

        $url = (string) config('demo.endpoints.create_subdomain');
        $payload = [
            'subdomain' => $client->sub_domain,
            'country' => (int) $client->country_id === 2 ? 'uz' : 'tj',
        ];

        try {
            $response = Http::timeout((int) config('demo.provisioning.subdomain_timeout', 90))
                ->acceptJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            $this->logs->logApiResponse(
                organizationId: null,
                clientId: (int) $client->id,
                action: 'create_subdomain',
                method: 'POST',
                url: $url,
                payload: $payload,
                error: $e->getMessage()
            );

            // Пользователю нужен внятный текст, а разбирающему логи — причина.
            // Без неё «не удалось подготовить» неотличимо от таймаута.
            Log::error('DemoProvisioner: createSubdomain unreachable', [
                'demo_request_id' => $request->id,
                'url' => $url,
                'subdomain' => $client->sub_domain,
                'error' => $e->getMessage(),
            ]);

            throw new DemoProvisioningException(
                'subdomain_failed',
                'Не удалось подготовить рабочее пространство. Попробуйте ещё раз через минуту.',
                retryable: true
            );
        }

        $this->logs->logApiResponse(
            organizationId: null,
            clientId: (int) $client->id,
            action: 'create_subdomain',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response
        );

        if (!$response->successful()) {
            Log::error('DemoProvisioner: createSubdomain rejected', [
                'demo_request_id' => $request->id,
                'url' => $url,
                'subdomain' => $client->sub_domain,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new DemoProvisioningException(
                'subdomain_failed',
                'Не удалось подготовить рабочее пространство. Попробуйте ещё раз через минуту.',
                retryable: $response->serverError()
            );
        }
    }

    /* ---------------------------------------------------------------- */
    /* Шаг 3. Организация в биллинге и в CRM                             */
    /* ---------------------------------------------------------------- */

    private function resolveOrganization(DemoRequest $request, Client $client): Organization
    {
        $request->markStep(DemoRequest::STEP_CRM);

        if ($request->organization_id && $organization = Organization::query()->find($request->organization_id)) {
            return $organization;
        }

        $organization = Organization::create([
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'client_id' => $client->id,
            'has_access' => true,
        ]);

        $request->update(['organization_id' => $organization->id]);

        return $organization;
    }

    /**
     * Создаёт организацию в CRM и возвращает одноразовый токен входа.
     *
     * null означает, что кабинет создан, но автовход недоступен: например,
     * CRM ещё не умеет выдавать токен. Это не ошибка — доступы уйдут письмом.
     */
    private function createOrganizationInCrm(
        DemoRequest $request,
        Client $client,
        Organization $organization,
        string $password
    ): ?string {
        $domain = config('services.sham.domain', 'shamcrm.com');
        $url = "https://{$client->sub_domain}-back.{$domain}/api/organization";
        $tariff = $this->demoTariff($client);

        $payload = [
            'name' => $organization->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'tariff_id' => $tariff?->id ?? (int) config('demo.tariff.default', 4),
            'user_count' => $tariff?->user_count,
            'project_count' => $tariff?->project_count,
            'b_organization_id' => $organization->id,
            'password' => $password,
            'is_demo' => true,
            'channels_count' => $tariff?->channels_count ?? 3,
            'issue_demo_login_token' => true,
        ];

        $attempts = max(1, (int) config('demo.provisioning.crm_attempts', 8));
        $delayMs = (int) config('demo.provisioning.crm_retry_delay_ms', 1500);
        $timeout = (int) config('demo.provisioning.crm_timeout', 20);

        $lastError = null;
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::timeout($timeout)->acceptJson()->post($url, $payload);
            } catch (\Throwable $e) {
                // Свежий поддомен ещё не резолвится и не проксируется —
                // это ожидаемо на первых секундах, поэтому повторяем.
                $lastError = $e->getMessage();
                $this->pause($attempt, $delayMs);
                continue;
            }

            $lastResponse = $response;

            if ($response->successful()) {
                $this->logs->logApiResponse(
                    organizationId: (int) $organization->id,
                    clientId: (int) $client->id,
                    action: 'create_organization',
                    method: 'POST',
                    url: $url,
                    payload: $payload,
                    response: $response
                );

                $token = $response->json('result.demo_login_token');

                if (!is_string($token) || $token === '') {
                    Log::warning('DemoProvisioner: CRM returned no demo login token', [
                        'demo_request_id' => $request->id,
                        'organization_id' => $organization->id,
                    ]);

                    return null;
                }

                return $token;
            }

            $lastError = 'HTTP ' . $response->status();

            // Ответ 4xx повторять бессмысленно: данные не станут валиднее.
            if (!$this->isRetryable($response)) {
                break;
            }

            $this->pause($attempt, $delayMs);
        }

        $this->logs->logApiResponse(
            organizationId: (int) $organization->id,
            clientId: (int) $client->id,
            action: 'create_organization',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $lastResponse,
            error: $lastError
        );

        Log::error('DemoProvisioner: CRM organization creation failed', [
            'demo_request_id' => $request->id,
            'url' => $url,
            'error' => $lastError,
        ]);

        throw new DemoProvisioningException(
            'crm_unreachable',
            'CRM пока не отвечает. Мы продолжим настройку и пришлём доступы на почту.',
            retryable: true
        );
    }

    private function isRetryable(Response $response): bool
    {
        return $response->serverError() || $response->status() === 429;
    }

    /** Линейный рост паузы: к последней попытке ждём заметно дольше. */
    private function pause(int $attempt, int $delayMs): void
    {
        usleep(min($attempt, 4) * $delayMs * 1000);
    }

    private function demoTariff(Client $client): ?Tariff
    {
        $byCountry = (array) config('demo.tariff.by_country', []);
        $default = (int) config('demo.tariff.default', 4);
        $tariffId = $byCountry[(int) $client->country_id] ?? $default;

        return Tariff::query()->find($tariffId) ?? Tariff::query()->find($default);
    }

    /* ---------------------------------------------------------------- */
    /* Уведомления и откат                                               */
    /* ---------------------------------------------------------------- */

    private function notify(Client $client, Organization $organization, string $password): void
    {
        SendSiteAccessEmailJob::dispatch($client, $organization, $password);

        SendToShamJob::dispatch(
            $client->phone,
            $this->demoTariff($client)?->name,
            $client->email,
            $client->name,
            $client->country?->name ?? Country::find($client->country_id)?->name,
            $client->partner?->id
        );
    }

    /**
     * Освобождаем email, чтобы клиент мог повторить попытку. Поддомен и базу
     * тенанта не трогаем: создание поддомена идемпотентно, а удаление базы —
     * необратимая операция, которой не место в обработке ошибок.
     */
    private function rollback(DemoRequest $request): void
    {
        try {
            if ($request->organization_id) {
                Organization::query()->whereKey($request->organization_id)->delete();
            }

            if ($request->client_id) {
                Client::query()->whereKey($request->client_id)->delete();
            }

            $request->update(['client_id' => null, 'organization_id' => null]);
        } catch (\Throwable $e) {
            Log::error('DemoProvisioner: rollback failed', [
                'demo_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
