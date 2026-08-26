<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\CreatePaymentLinkRequest;
use App\Services\Site\SitePaymentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SitePaymentController extends Controller
{
    public function createLink(CreatePaymentLinkRequest $request, SitePaymentLinkService $service): JsonResponse
    {
        try {
            return response()->json($service->create($request->validated()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Не удалось создать ссылку на оплату.',
            ], 422);
        }
    }
}
