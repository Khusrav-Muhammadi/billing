<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Партнёр, чья заявка с портала ещё не одобрена (или отклонена), не работает с API клиентов, КП и оплат.
 * Логин и профиль ему доступны: портал показывает экран «Заявка на рассмотрении».
 */
class EnsurePartnerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && mb_strtolower((string) $user->role) === 'partner'
            && (string) ($user->approval_status ?? 'approved') !== 'approved') {
            return response()->json(['message' => 'Заявка партнёра ещё не одобрена.'], 403);
        }

        return $next($request);
    }
}
