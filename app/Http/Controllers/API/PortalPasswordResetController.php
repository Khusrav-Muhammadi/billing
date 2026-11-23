<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PartnerProgram\PortalPasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «Забыли пароль» партнёрского портала (middleware partners.token, вызывает только портал).
 */
class PortalPasswordResetController extends Controller
{
    public function __construct(private readonly PortalPasswordResetService $service)
    {
    }

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($this->service->start($data['login']));
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $this->service->reset($data['login'], $data['code'], $data['password']);

        return response()->json(['success' => true]);
    }
}
