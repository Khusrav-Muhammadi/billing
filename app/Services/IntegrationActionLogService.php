<?php

namespace App\Services;

use App\Models\IntegrationActionLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class IntegrationActionLogService
{
    public function logApiResponse(
        ?int $organizationId,
        ?int $clientId,
        string $action,
        string $method,
        string $url,
        array $payload,
        ?Response $response = null,
        ?string $error = null,
        ?int $commercialOfferId = null
    ): void {
        $this->create([
            'organization_id' => $organizationId,
            'client_id' => $clientId,
            'commercial_offer_id' => $commercialOfferId,
            'type' => 'api',
            'action' => $action,
            'method' => strtoupper($method),
            'url' => $url,
            'status_code' => $response?->status(),
            'successful' => $response?->successful(),
            'payload' => $this->safeArray($payload),
            'response' => $this->responseBody($response),
            'error' => $error,
            'occurred_at' => now(),
        ]);
    }

    public function logEmail(
        ?int $organizationId,
        ?int $clientId,
        string $action,
        string $recipient,
        string $subject,
        array $payload,
        bool $successful,
        ?string $error = null,
        ?int $commercialOfferId = null
    ): void {
        $this->create([
            'organization_id' => $organizationId,
            'client_id' => $clientId,
            'commercial_offer_id' => $commercialOfferId,
            'type' => 'email',
            'action' => $action,
            'recipient' => $recipient,
            'subject' => $subject,
            'successful' => $successful,
            'payload' => $this->safeArray($payload),
            'error' => $error,
            'occurred_at' => now(),
        ]);
    }

    private function create(array $data): void
    {
        try {
            IntegrationActionLog::query()->create($data);
        } catch (\Throwable $e) {
            Log::error('IntegrationActionLogService: failed to write log', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    private function responseBody(?Response $response): ?array
    {
        if (!$response) {
            return null;
        }

        try {
            $json = $response->json();
            if (is_array($json)) {
                return $json;
            }
        } catch (\Throwable) {
        }

        return ['body' => mb_substr((string)$response->body(), 0, 5000)];
    }

    private function safeArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array((string)$key, ['password', 'token', 'api_key', 'Authorization'], true)) {
                $payload[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->safeArray($value);
                continue;
            }

            if ($value instanceof \JsonSerializable) {
                $payload[$key] = $value->jsonSerialize();
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $payload[$key] = $value->format(DATE_ATOM);
                continue;
            }

            if (is_object($value)) {
                $payload[$key] = method_exists($value, 'toArray') ? $value->toArray() : (string)get_class($value);
            }
        }

        return $payload;
    }
}
