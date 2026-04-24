<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "email" => ["required", "email"],
            "password" => "required",
            "device_name" => ["required", "string"],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $token = $this->authService->mobileLogin(
            $request->only("email", "password"),
            $request->device_name,
        );

        return response()->json([
            "token" => $token,
            "token_type" => "Bearer",
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "name" => "required|string|max:255",
            "tag" => "required|string|max:255|unique:users",
            "email" => "required|string|email|max:255|unique:users",
            "password" => "required|string|min:8|confirmed",
            "device_name" => "required|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $result = $this->authService->mobileRegister(
            $request->only("name", "tag", "email", "password"),
            $request->device_name,
        );

        event(new Registered($result["user"]));

        return response()->json(
            [
                "message" => "User registered successfully",
                "user" => new UserResource($result["user"]),
                "token" => $result["token"],
                "token_type" => "Bearer",
            ],
            201,
        );
    }

    public function logout(Request $request)
    {
        $this->authService->revokeToken($request->user());

        return response()->json([
            "message" => "Token revoked successfully",
        ]);
    }

    public function revokeAllTokens(Request $request)
    {
        $this->authService->revokeAllTokens($request->user());

        return response()->json([
            "message" => "All tokens revoked successfully",
        ]);
    }

    public function tokens(Request $request)
    {
        return response()->json([
            "tokens" => $request->user()->tokens->map(fn($token) => [
                "id" => $token->id,
                "name" => $token->name,
                "last_used_at" => $token->last_used_at,
                "created_at" => $token->created_at,
            ]),
        ]);
    }

    public function revokeSpecificToken(Request $request)
    {
        $request->validate([
            "token_id" => "required|integer",
        ]);

        $this->authService->revokeToken($request->user(), $request->token_id);

        return response()->json([
            "message" => "Token revoked successfully",
        ]);
    }
}
