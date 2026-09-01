<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\CreateSiteExtraServicesRequest;
use App\Services\Site\SiteOfferCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SiteExtraServicesController extends Controller
{
    public function store(CreateSiteExtraServicesRequest $request, SiteOfferCheckoutService $checkout): JsonResponse
    {
        try {
            return response()->json($checkout->extraServices($request->validated()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Не удалось создать оплату доп. услуг.',
            ], 422);
        }
    }
}
