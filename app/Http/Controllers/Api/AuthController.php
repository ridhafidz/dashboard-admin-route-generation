<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        if (!$user->hasRole('driver')) {
            return response()->json(['message' => 'Akun ini tidak memiliki akses driver'], 403);
        }

        $token = $user->createToken('driver-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'driver' => $user->driver ? [
                'id' => $user->driver->uuid,
                'status' => $user->driver->status,
            ] : null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }
}