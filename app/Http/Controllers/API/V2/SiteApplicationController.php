<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Services\Site\InstantDemoProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SiteApplicationController extends Controller
{
    public function store(Request $request, InstantDemoProvisioner $provisioner): JsonResponse
    {
        set_time_limit(120);

        $validated = $this->validateRequest($request);

        if ($validated['request_type'] !== 'demo') {
            throw ValidationException::withMessages([
                'request_type' => 'Этот метод принимает только заявки на демо.',
            ]);
        }

        $conflict = $provisioner->findConflict($validated);
        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => $conflict['message'],
            ], 409);
        }

        try {
            $result = $provisioner->provision($validated);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'login_url' => $result['login_url'],
        ]);
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'fio' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => ['required', 'email', 'max:255'],
            'region_id' => 'nullable|integer',
            'request_type' => 'required|string|in:demo',
            'partner_id' => 'nullable|integer',
            'manager_id' => 'nullable|integer',
        ], [
            'fio.required' => 'Поле ФИО обязательно для заполнения.',
            'phone.required' => 'Поле телефон обязательно для заполнения.',
            'email.required' => 'Поле email обязательно для демо-аккаунта.',
            'email.email' => 'Пожалуйста введите правильный адрес почты',
            'request_type.in' => 'Поле тип заявки должно быть demo.',
        ]);
    }
}
