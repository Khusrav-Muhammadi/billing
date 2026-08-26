<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\CreateSiteRenewalRequest;
use App\Services\Site\SiteOfferCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SiteRenewalController extends Controller
{
    public function store(CreateSiteRenewalRequest $request, SiteOfferCheckoutService $checkout): JsonResponse
    {
        try {
            return response()->json($checkout->renewal($request->validated()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Не удалось создать продление тарифа.',
            ], 422);
        }
    }
}
