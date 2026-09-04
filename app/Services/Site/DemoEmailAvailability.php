<?php

namespace App\Services\Site;

use App\Models\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Проверка email перед выдачей демо.
 *
 * Сайт вызывает её на каждом изменении поля, поэтому проверка должна быть
 * быстрой: только формат, DNS домена, чёрный список одноразовых сервисов и
 * поиск дубликата. SMTP-пробы намеренно нет — порт 25 наружу обычно закрыт,
 * а грейлистинг делает результат недетерминированным.
 */
class DemoEmailAvailability
{
    public const REASON_INVALID = 'invalid';
    public const REASON_DISPOSABLE = 'disposable';
    public const REASON_UNKNOWN_DOMAIN = 'unknown_domain';
    public const REASON_TAKEN = 'taken';

    private const DNS_CACHE_TTL_SECONDS = 3600;

    /**
     * @return array{available: bool, reason: ?string, message: ?string}
     */
    public function check(string $email): array
    {
        $email = self::normalize($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            return $this->unavailable(
                self::REASON_INVALID,
                'Проверьте адрес почты — кажется, в нём опечатка.'
            );
        }

        $domain = strtolower((string) substr(strrchr($email, '@'), 1));

        if ($this->isDisposable($domain)) {
            return $this->unavailable(
                self::REASON_DISPOSABLE,
                'Одноразовые адреса не подходят: на эту почту придут доступы к аккаунту.'
            );
        }

        if (!$this->domainAcceptsMail($domain)) {
            return $this->unavailable(
                self::REASON_UNKNOWN_DOMAIN,
                'Домен ' . $domain . ' не принимает почту. Проверьте адрес.'
            );
        }

        if ($this->isTaken($email)) {
            return $this->unavailable(
                self::REASON_TAKEN,
                'На этот email уже есть аккаунт. Войдите или восстановите доступ на странице входа.'
            );
        }

        return [
            'available' => true,
            'reason' => null,
            'message' => null,
        ];
    }

    /** Единая форма адреса: по ней и ищем дубликаты, и сохраняем заявку. */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Занят ли адрес. Учитываем и биллинг, и центральную базу CRM: аккаунт
     * может существовать в CRM, но не иметь записи в биллинге.
     */
    public function isTaken(string $email): bool
    {
        if (Client::query()->where('email', $email)->exists()) {
            return true;
        }

        return $this->existsInCrm($email);
    }

    private function isDisposable(string $domain): bool
    {
        $blocked = (array) config('demo.disposable_email_domains', []);

        return in_array($domain, array_map('strtolower', $blocked), true);
    }

    private function domainAcceptsMail(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        return Cache::remember(
            'demo:email-dns:' . $domain,
            self::DNS_CACHE_TTL_SECONDS,
            fn () => checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')
        );
    }

    /**
     * Центральная база CRM. Недоступность проверки не должна блокировать
     * выдачу демо, поэтому при любой ошибке считаем, что адрес свободен —
     * дубликат всё равно поймается при создании организации.
     */
    private function existsInCrm(string $email): bool
    {
        $url = rtrim((string) config('demo.crm_check_email_url'), '/');

        if ($url === '') {
            return false;
        }

        try {
            // CRM принимает и GET, и POST; для проверки берём GET.
            $response = Http::timeout(5)->acceptJson()->get($url, ['email' => $email]);
        } catch (\Throwable $e) {
            Log::warning('DemoEmailAvailability: CRM email check failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (!$response->successful()) {
            return false;
        }

        // Эндпоинт CRM отдаёт голый boolean; более новые обёртки — объект.
        $body = $response->json();

        if (is_bool($body)) {
            return $body;
        }

        return (bool) ($body['exists'] ?? $body['result'] ?? false);
    }

    /**
     * @return array{available: bool, reason: string, message: string}
     */
    private function unavailable(string $reason, string $message): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'message' => $message,
        ];
    }
}
