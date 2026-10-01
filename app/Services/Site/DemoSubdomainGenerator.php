<?php

namespace App\Services\Site;

use App\Models\Client;
use Illuminate\Support\Str;

/**
 * Поддомен тенанта по email клиента.
 *
 * Новые демо создаются без суффикса `-new`. Старые клиенты с `-new`
 * в поддомене остаются как есть, этот генератор их не переименовывает.
 */
class DemoSubdomainGenerator
{
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
        return $this->base($email);
    }

    /**
     * Свободный поддомен: при коллизии добавляем числовой суффикс, а не
     * отказываем клиенту — два человека из одной компании имеют право на
     * собственные демо.
     */
    public function generateUnique(string $email): string
    {
        $base = $this->base($email);
        $candidate = $base;

        for ($i = 2; $i <= self::MAX_ATTEMPTS && $this->taken($candidate); $i++) {
            $candidate = $base . $i;
        }

        if ($this->taken($candidate)) {
            $candidate = $base . Str::lower(Str::random(4));
        }

        return $candidate;
    }

    private function taken(string $subdomain): bool
    {
        return Client::query()->where('sub_domain', $subdomain)->exists();
    }
}
