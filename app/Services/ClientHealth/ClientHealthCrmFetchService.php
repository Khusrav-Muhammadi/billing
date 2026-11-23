<?php

namespace App\Services\ClientHealth;

use App\Models\Client;
use App\Services\IntegrationActionLogService;
use App\Support\CrmHttp;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Сырые цифры для индекса здоровья из CRM клиента:
 * GET https://{sub}-back.shamcrm.com/api/billing/health-metrics?date=Y-m-d (X-Service-Token).
 *
 * В журнал интеграций (integration_action_logs) пишем только ошибки: успешных вызовов
 * каждую ночь по числу клиентов, они только засорят карточку организации.
 */
class ClientHealthCrmFetchService
{
    public function __construct(private readonly IntegrationActionLogService $logService)
    {
    }

    /**
     * @throws RuntimeException если CRM не ответила или ответила не 2xx
     */
    public function fetch(Client $client, Carbon $date, ?int $organizationId = null): array
    {
        $url = CrmHttp::tenantUrl((string) $client->sub_domain, (string) config('services.sham.domain'), '/api/billing/health-metrics');
        $params = ['date' => $date->toDateString()];

        try {
            $response = CrmHttp::client()->timeout(60)->get($url, $params);
        } catch (Throwable $e) {
            $this->log($client, $organizationId, $url, $params, null, $e->getMessage());

            throw new RuntimeException('CRM недоступна: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful()) {
            $this->log($client, $organizationId, $url, $params, $response);

            throw new RuntimeException('CRM ответила ' . $response->status());
        }

        $json = $response->json();
        $result = is_array($json) ? ($json['result'] ?? $json['data'] ?? null) : null;

        if (!is_array($result) || !array_key_exists('users_total', $result) || !is_array($result['weeks'] ?? null)) {
            $this->log($client, $organizationId, $url, $params, $response, 'Неожиданный формат ответа');

            throw new RuntimeException('Неожиданный формат ответа CRM');
        }

        return $result;
    }

    private function log(Client $client, ?int $organizationId, string $url, array $params, $response = null, ?string $error = null): void
    {
        try {
            $this->logService->logApiResponse(
                organizationId: $organizationId,
                clientId: (int) $client->id,
                action: 'client_health_metrics',
                method: 'GET',
                url: $url,
                payload: $params,
                response: $response,
                error: $error
            );
        } catch (Throwable) {
            // Журнал не должен ронять ночной расчёт.
        }
    }
}
