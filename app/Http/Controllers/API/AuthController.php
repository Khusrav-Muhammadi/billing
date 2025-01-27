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
        $data = $request->validated();

        if (Auth::attempt($data)) {
            $user = Auth::user();
            $token = $user->createToken('PartnerToken');

            return response()->json(['token' => $token->plainTextToken, 'user' => $user], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
