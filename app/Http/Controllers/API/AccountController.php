<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = Account::query()
            ->with('currency:id,symbol_code,name')
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);

        return response()->json([
            'accounts' => $accounts,
        ]);
    }
}

