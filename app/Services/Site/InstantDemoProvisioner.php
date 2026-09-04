<?php

namespace App\Services\Site;

use App\Jobs\SendSiteAccessEmailJob;
use App\Jobs\SendToShamJob;
use App\Models\Client;
use App\Models\Country;
use App\Models\Organization;
use App\Models\Tariff;
use App\Services\IntegrationActionLogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class InstantDemoProvisioner
{
    public function __construct(private IntegrationActionLogService $logs)
    {
    }

    public function provision(array $data): array
    {
        $client = $this->createClient($data);
        if (!$client) {
            throw new RuntimeException('Не удалось создать демо-аккаунт. Попробуйте позже.');
        }

        try {
            $this->createSubdomain($client);
        } catch (\Throwable $e) {
            $client->delete();
            throw $e;
        }

        $organization = Organization::create([
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'client_id' => $client->id,
            'has_access' => true,
        ]);

        $password = Str::random(12);
        $loginToken = $this->createOrganizationInCrm($client, $organization, $password);

        SendSiteAccessEmailJob::dispatch($client, $organization, $password);
        SendToShamJob::dispatch(
            $client->phone,
            $this->demoTariff($client)?->name,
            $client->email,
            $client->name,
            $client->country?->name ?? Country::find($client->country_id)?->name,
            $client->partner?->id
        );

        if (!$loginToken) {
            throw new RuntimeException('Аккаунт создан, но вход пока недоступен. Проверьте почту или попробуйте позже.');
        }

        return [
            'login_url' => rtrim($client->crmUrl(), '/') . '/demo-login?token=' . urlencode($loginToken),
            'client' => $client,
        ];
    }

    public function findConflict(array $data): ?array
    {
        $email = $data['email'] ?? null;
        $subdomain = $email ? $this->generateSubdomain($email) : null;

        if ($email && Client::query()->where('email', $email)->exists()) {
            return [
                'message' => 'Этот email уже используется в системе. Если это вы — восстановите доступ через форму входа.',
            ];
        }

        if ($subdomain && Client::query()->where('sub_domain', $subdomain)->exists()) {
            return [
                'message' => 'Пользователь с таким поддоменом уже существует.',
            ];
        }

        return null;
    }

    private function createClient(array $data): ?Client
    {
        $countryId = $data['region_id'] ?? 1;

        $clientData = [
            'name' => $data['fio'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'country_id' => $countryId,
            'is_demo' => true,
            'sub_domain' => $this->generateSubdomain($data['email']),
            'manager_id' => $data['manager_id'] ?? null,
            'partner_id' => is_numeric($data['partner_id'] ?? null) ? (int) $data['partner_id'] : null,
        ];

        if (!empty($clientData['manager_id'])) {
            $manager = \App\Models\User::query()->find($clientData['manager_id']);
            if ($manager && !empty($manager->partner_id)) {
                $clientData['partner_id'] = $manager->partner_id;
            }
        }

        try {
            return Client::create($clientData);
        } catch (\Exception $e) {
            Log::error('InstantDemoProvisioner: failed to create client', [
                'error' => $e->getMessage(),
                'data' => $clientData,
            ]);

            return null;
        }
    }

    public function generateSubdomain(string $email): string
    {
        $email = $email . '-new';
        [$local, $domain] = explode('@', $email);
        $isPublic = in_array(strtolower($domain), config('app.public_domains'));

        return Str::of($isPublic ? $local : $local . $domain)
            ->replace('_', '')
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->trim('-')
            ->replaceMatches('/-+/', '-')
            ->whenEmpty(fn () => 'default');
    }

    private function createSubdomain(Client $client): void
    {
        $url = 'https://shamcrm.com/api/createSubdomain';
        $payload = [
            'subdomain' => $client->sub_domain,
            'country' => (int) $client->country_id === 2 ? 'uz' : 'tj',
        ];

        try {
            $response = Http::timeout(90)
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

            throw new RuntimeException('Не удалось создать поддомен. Попробуйте позже.');
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
            throw new RuntimeException('Не удалось создать поддомен. Попробуйте позже.');
        }
    }

    private function demoTariff(Client $client): ?Tariff
    {
        $tariffId = ((int) $client->country_id === 2) ? 8 : 4;

        return Tariff::query()->find($tariffId) ?? Tariff::query()->find(4);
    }

    private function createOrganizationInCrm(Client $client, Organization $organization, string $password): ?string
    {
        $domain = config('services.sham.domain', 'shamcrm.com');
        $url = "https://{$client->sub_domain}-back.{$domain}/api/organization";
        $tariff = Tariff::query()->where('id', 4)->first();

        $payload = [
            'name' => $organization->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'tariff_id' => $tariff?->id ?? 4,
            'user_count' => $tariff?->user_count,
            'project_count' => $tariff?->project_count,
            'b_organization_id' => $organization->id,
            'password' => $password,
            'is_demo' => true,
            'channels_count' => $tariff?->channels_count ?? 3,
            'issue_demo_login_token' => true,
        ];

        $lastError = null;
        $response = null;

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            try {
                $response = Http::timeout(20)
                    ->acceptJson()
                    ->post($url, $payload);

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

                    return is_string($token) && $token !== '' ? $token : null;
                }

                $lastError = 'HTTP ' . $response->status();
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            usleep(1500000);
        }

        $this->logs->logApiResponse(
            organizationId: (int) $organization->id,
            clientId: (int) $client->id,
            action: 'create_organization',
            method: 'POST',
            url: $url,
            payload: $payload,
            response: $response,
            error: $lastError
        );

        return null;
    }
}
