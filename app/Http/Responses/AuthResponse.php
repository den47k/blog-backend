<?php

namespace App\Http\Responses;

use App\Enums\AuthStatus;
use App\Http\Resources\User\AuthenticatedUserResource;
use App\Services\Auth\AuthResult;
use Illuminate\Http\JsonResponse;

class AuthResponse
{
    public static function from(AuthResult $result, int $loggedInStatus = 200): JsonResponse
    {
        return match ($result->status) {
            AuthStatus::LoggedIn => response()->json([
                'user' => new AuthenticatedUserResource($result->user),
                'token' => $result->token,
                'token_type' => 'Bearer',
                'device_id' => $result->device->id,
            ], $loggedInStatus),

            AuthStatus::TwoFactorRequired => response()->json([
                'requires_2fa' => true,
                'challenge_token' => $result->token,
                'token_type' => 'Bearer',
                'expires_in' => $result->expiresIn,
            ]),

            AuthStatus::LinkRequired => response()->json([
                'status' => $result->status->value,
                'link_token' => $result->token,
                'token_type' => 'Bearer',
                'expires_in' => $result->expiresIn,
            ], 409),

            AuthStatus::PendingEmail, AuthStatus::PendingProfile => response()->json([
                'status' => $result->status->value,
                'registration_token' => $result->token,
                'token_type' => 'Bearer',
                'expires_in' => $result->expiresIn,
            ]),
        };
    }
}
