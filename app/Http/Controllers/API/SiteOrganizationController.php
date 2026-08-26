<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Site\SiteOrganizationStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteOrganizationController extends Controller
{
    public function show(Request $request, SiteOrganizationStatusService $status): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $status->build((int) $validated['organization_id'])
        );
    }
}
