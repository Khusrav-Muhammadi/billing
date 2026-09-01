<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\CreateSiteAiTopUpRequest;
use App\Services\Site\SiteOfferCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SiteAiTopUpController extends Controller
{
    public function store(CreateSiteAiTopUpRequest $request, SiteOfferCheckoutService $checkout): JsonResponse
    {
        try {
            return response()->json($checkout->aiTopUp($request->validated()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Не удалось создать пополнение ИИ-счёта.',
            ], 422);
        }
    }
}
