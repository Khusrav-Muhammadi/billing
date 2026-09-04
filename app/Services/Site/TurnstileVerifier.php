<?php

namespace App\Services\Site;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Проверка капчи Cloudflare Turnstile.
 *
 * Недоступность Cloudflare трактуется в пользу пользователя: терять живые
 * заявки из-за чужого сбоя дороже, чем пропустить редкого бота.
 */
class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isConfigured(): bool
    {
        return filled(config('demo.turnstile.secret_key'));
    }

    public function isRequired(): bool
    {
        return $this->isConfigured() && (bool) config('demo.turnstile.required');
    }

    public function passes(?string $token, ?string $ip = null): bool
    {
        if (!$this->isConfigured()) {
            return true;
        }

        if (blank($token)) {
            return !$this->isRequired();
        }

        try {
            $response = Http::timeout(5)->asForm()->post(self::ENDPOINT, array_filter([
                'secret' => config('demo.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (\Throwable $e) {
            Log::warning('TurnstileVerifier: verification request failed', ['error' => $e->getMessage()]);

            return true;
        }

        if (!$response->successful()) {
            Log::warning('TurnstileVerifier: unexpected response', ['status' => $response->status()]);

            return true;
        }

        return (bool) $response->json('success', false);
    }
}
