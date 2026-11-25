<?php

namespace App\Services\PartnerProgram;

use App\Jobs\SendPortalPasswordResetCodeJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * «Забыли пароль» в партнёрском портале: код на почту пользователя биллинга и новый пароль.
 *
 * 1. start(login): по логину или email ищем пользователя и шлём 6-значный код на его почту.
 *    Ответ всегда одинаковый, есть такой пользователь или нет: по нему нельзя перебирать почты.
 * 2. reset(login, code, password): проверяем код (15 минут, 5 попыток), меняем пароль
 *    и отзываем все API-токены пользователя: остальные сессии портала выйдут.
 *
 * Код хранится только как sha256 в кэше. Тот же подход, что в PartnerOwnDemoService.
 */
class PortalPasswordResetService
{
    public const CODE_TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;
    private const MAX_CODES_PER_WINDOW = 3;

    public function start(string $login): array
    {
        $login = $this->normalize($login);
        $this->hit('send', $login, self::MAX_CODES_PER_WINDOW, self::CODE_TTL_MINUTES * 60, 'login');

        $user = $this->findUser($login);
        if ($user) {
            $code = (string) random_int(100000, 999999);
            Cache::put($this->codeKey($login), [
                'user_id' => (int) $user->id,
                'hash' => hash('sha256', $code),
                'attempts' => 0,
            ], now()->addMinutes(self::CODE_TTL_MINUTES));

            SendPortalPasswordResetCodeJob::dispatch((string) $user->email, $code, (string) $user->name, self::CODE_TTL_MINUTES);
        } else {
            Log::info('Portal password reset: user not found', ['login_hash' => hash('sha256', $login)]);
        }

        return [
            'ttl_minutes' => self::CODE_TTL_MINUTES,
            // Почту показываем замаскированной и только если пользователь найден.
            'email_hint' => $user ? $this->mask((string) $user->email) : null,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function reset(string $login, string $code, string $password): void
    {
        $login = $this->normalize($login);
        $key = $this->codeKey($login);
        $pending = Cache::get($key);

        if (!$pending) {
            throw ValidationException::withMessages(['code' => 'Код истёк или не запрашивался. Запросите новый.']);
        }

        if (!hash_equals($pending['hash'], hash('sha256', trim($code)))) {
            $pending['attempts']++;
            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);
                throw ValidationException::withMessages(['code' => 'Слишком много неверных попыток. Запросите новый код.']);
            }
            Cache::put($key, $pending, now()->addMinutes(self::CODE_TTL_MINUTES));
            throw ValidationException::withMessages(['code' => 'Неверный код.']);
        }

        $user = User::query()->find($pending['user_id']);
        if (!$user || !$this->allowed($user)) {
            Cache::forget($key);
            throw ValidationException::withMessages(['code' => 'Код истёк или не запрашивался. Запросите новый.']);
        }

        DB::transaction(function () use ($user, $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });

        Cache::forget($key);
        RateLimiter::clear($this->limiterKey('send', $login));
    }

    private function findUser(string $login): ?User
    {
        $user = User::query()
            ->where(fn ($q) => $q->whereRaw('LOWER(login) = ?', [$login])->orWhereRaw('LOWER(email) = ?', [$login]))
            ->orderByRaw('LOWER(login) = ? DESC', [$login])
            ->first();

        return $user && $this->allowed($user) && filter_var($user->email, FILTER_VALIDATE_EMAIL) ? $user : null;
    }

    /** Отклонённые заявки партнёров в портал не входят (как в API\AuthController::login), им и сброс не нужен. */
    private function allowed(User $user): bool
    {
        return !($user->role === 'partner' && ($user->approval_status ?? 'approved') === 'rejected');
    }

    private function hit(string $action, string $login, int $max, int $decaySeconds, string $field): void
    {
        $key = $this->limiterKey($action, $login);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
            throw ValidationException::withMessages([$field => "Слишком много запросов кода. Попробуйте через {$minutes} мин."]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function normalize(string $login): string
    {
        return mb_strtolower(trim($login));
    }

    private function codeKey(string $login): string
    {
        return 'portal_password_reset_' . hash('sha256', $login);
    }

    private function limiterKey(string $action, string $login): string
    {
        return 'portal_password_reset_' . $action . '_' . hash('sha256', $login);
    }

    /** ivan.petrov@gmail.com -> iv***@gmail.com */
    private function mask(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, min(2, mb_strlen($name))) . '***@' . $domain;
    }
}
