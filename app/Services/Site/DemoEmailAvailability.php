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
    public const REASON_SUBDOMAIN = 'subdomain';

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

        // fingroupcrm@gmail.com превращается в тенант fingroupcrm-back.
        // Если такой тенант уже есть, CRM раньше молча возвращала его, и демо
        // садилось в чужой кабинет. Здесь отказываем до создания заявки.
        if ($this->subdomainTaken($email)) {
            return $this->unavailable(
                self::REASON_SUBDOMAIN,
                'Пользователь с таким поддоменом уже существует. Укажите другой email.'
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
        if ($this->clientExistsByEmail($email)) {
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

    private function existsInCrm(string $email): bool
    {
        $url = rtrim((string) config('demo.crm_check_email_url'), '/');

        if ($url === '') {
            return false;
        }

        try {
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

        $body = $response->json();

        if (is_bool($body)) {
            return $body;
        }

        return (bool) ($body['exists'] ?? $body['result'] ?? false);
    }

    /**
     * Занят ли поддомен, который получится из этого email.
     * Смотрим биллинг и CRM: тенант может жить в CRM без карточки клиента.
     */
    private function subdomainTaken(string $email): bool
    {
        $subdomain = app(DemoSubdomainGenerator::class)->generate($email);

        if ($subdomain === '') {
            return false;
        }

        if ($this->clientExistsBySubdomain($subdomain)) {
            return true;
        }

        return $this->subdomainExistsInCrm($subdomain);
    }

    protected function clientExistsByEmail(string $email): bool
    {
        return Client::query()->where('email', $email)->exists();
    }

    protected function clientExistsBySubdomain(string $subdomain): bool
    {
        return Client::query()->where('sub_domain', $subdomain)->exists();
    }

    private function subdomainExistsInCrm(string $subdomain): bool
    {
        $url = rtrim((string) config('demo.crm_check_subdomain_url'), '/');

        if ($url === '') {
            return false;
        }

        try {
            $response = Http::timeout(5)->acceptJson()->post($url, [
                'domain' => $subdomain,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DemoEmailAvailability: CRM subdomain check failed', [
                'subdomain' => $subdomain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (!$response->successful()) {
            return false;
        }

        $body = $response->json();

        if (is_bool($body)) {
            return $body;
        }

        return (bool) ($body['result'] ?? $body['exists'] ?? false);
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
