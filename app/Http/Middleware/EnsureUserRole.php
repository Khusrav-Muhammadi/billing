<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ только пользователям с одной из ролей: ->middleware('role:admin').
 *
 * В биллинге одна таблица users на всех: admin — сотрудник shamCRM, partner — партнёр
 * (в том числе самозарегистрированный с портала), manager — сотрудник партнёра (users.partner_id).
 * Без этой проверки любой вошедший, включая партнёра на рассмотрении, получал права админа.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = mb_strtolower(trim((string) ($request->user()?->role ?? '')));

        if ($role === '' || !in_array($role, array_map('mb_strtolower', $roles), true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Недостаточно прав.'], 403);
            }

            abort(403, 'Недостаточно прав.');
        }

        return $next($request);
    }
}
