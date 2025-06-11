<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register($request->validated());

        event(new Registered($user));

        return response()->json([
            'message' => 'User registered. Verification email sent.',
            'user' => $user
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $this->authService->login($request);
        $request->session()->regenerate();

        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $this->authService->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}
