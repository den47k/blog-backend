<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\User\AuthenticatedUserResource;
use App\Services\Auth\CredentialAuthenticator;
use App\Services\Auth\DeviceService;
use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SessionController extends Controller
{
    public const TWO_FACTOR_CHALLENGE_TTL_MINUTES = 5;

    public function __construct(
        private readonly CredentialAuthenticator $credentialAuthenticator,
        private readonly TokenService $tokenService,
        private readonly DeviceService $deviceService,
    ) {}

    public function login(LoginRequest $request)
    {
        $credentials = $request->credentials();

        $user = $this->credentialAuthenticator->authenticate($credentials['email'], $credentials['password']);

        if ($user->status !== UserStatus::Active) {
            return response()->json([
                'message' => 'Account is not active.',
                'status' => $user->status->value,
            ], 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            $challenge = $this->tokenService->issueToken(
                $user,
                '2fa-challenge',
                [TokenAbility::TwoFactorPending->value],
                Carbon::now()->addMinutes(self::TWO_FACTOR_CHALLENGE_TTL_MINUTES),
            );

            return response()->json([
                'requires_2fa' => true,
                'challenge_token' => $challenge,
                'token_type' => 'Bearer',
                'expires_in' => self::TWO_FACTOR_CHALLENGE_TTL_MINUTES * 60,
            ]);
        }

        $result = $this->deviceService->issueForUser($user, $request->deviceName(), $request);

        return response()->json([
            'user' => new AuthenticatedUserResource($user),
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'device_id' => $result['device']->id,
        ]);
    }

    public function logout(Request $request)
    {
        $this->tokenService->revokeCurrent($request->user());

        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request)
    {
        $this->tokenService->revokeAll($request->user());

        return response()->json(['message' => 'Logged out of all devices']);
    }
}
