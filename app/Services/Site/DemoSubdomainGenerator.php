<?php

namespace App\Services\Site;

use App\Models\Client;
use Illuminate\Support\Str;

/**
 * Поддомен тенанта по email клиента.
 *
 * Суффикс `-new` обязателен: по нему инфраструктура отличает тенантов,
 * которые обслуживает переработанный фронтенд, от старых.
 */
class DemoSubdomainGenerator
{
    public const SUFFIX = '-new';

    private const MAX_BASE_LENGTH = 40;
    private const MAX_ATTEMPTS = 50;

    /** Основа поддомена без суффикса и без проверки на занятость. */
    public function base(string $email): string
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            $local = $email;
            $domain = '';
        } else {
            $local = substr($email, 0, $at);
            $domain = substr($email, $at + 1);
        }

        // Для публичных почтовиков домен в поддомене только мешает: из
        // ivan@gmail.com получается ivan, а не ivangmailcom.
        $isPublic = in_array(strtolower($domain), (array) config('app.public_domains'), true);

        return (string) Str::of($isPublic ? $local : $local . $domain)
            ->replace('_', '')
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->whenEmpty(fn () => Str::of('demo'))
            ->limit(self::MAX_BASE_LENGTH, '');
    }

    /** Поддомен-кандидат без учёта занятости. */
    public function generate(string $email): string
    {
        return $this->base($email) . self::SUFFIX;
    }

    /**
     * Свободный поддомен: при коллизии добавляем числовой суффикс, а не
     * отказываем клиенту — два человека из одной компании имеют право на
     * собственные демо.
     */
    public function generateUnique(string $email): string
    {
        $base = $this->base($email);
        $candidate = $base . self::SUFFIX;

        for ($i = 2; $i <= self::MAX_ATTEMPTS && $this->taken($candidate); $i++) {
            $candidate = $base . $i . self::SUFFIX;
        }

        if ($this->taken($candidate)) {
            $candidate = $base . Str::lower(Str::random(4)) . self::SUFFIX;
        }

        return $candidate;
    }

    private function taken(string $subdomain): bool
    {
        return Client::query()->where('sub_domain', $subdomain)->exists();
    }
}
