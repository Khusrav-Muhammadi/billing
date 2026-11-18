<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        if (Auth::attempt($request->credentials())) {
            $user = Auth::user();

            // Партнёрский портал: отклонённые заявки не входят. Ожидающие входят и видят «на рассмотрении».
            if ($user->role === 'partner' && ($user->approval_status ?? 'approved') === 'rejected') {
                Auth::logout();

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $token = $user->createToken('PartnerToken');

            return response()->json([
                'token' => $token->plainTextToken,
                'user' => $user->load([
                    'country:id,name,currency_id',
                    'country.currency:id,name,symbol_code',
                    'procents' => function ($q) {
                        $q->latest()->limit(1);
                    },
                ]),
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

}
