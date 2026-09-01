<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Site\SiteCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteCatalogController extends Controller
{
    public function show(Request $request, SiteCatalogService $catalog): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'min:1'],
            'date' => ['nullable', 'date'],
        ]);

        return response()->json(
            $catalog->build(
                (int) $validated['organization_id'],
                $validated['date'] ?? null
            )
        );
    }
}
