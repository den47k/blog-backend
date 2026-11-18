<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\User\AuthenticatedUserResource;
use App\Http\Responses\AuthResponse;
use App\Services\Auth\CredentialAuthenticator;
use App\Services\Auth\DeviceService;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly CredentialAuthenticator $credentialAuthenticator,
        private readonly TokenService $tokenService,
        private readonly DeviceService $deviceService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->credentials();

        $user = $this->credentialAuthenticator->authenticate($credentials['email'], $credentials['password']);

        if ($user->status !== UserStatus::Active) {
            return response()->json([
                'message' => 'Account is not active.',
                'status' => $user->status->value,
            ], 403);
        }

        return AuthResponse::from(
            $this->deviceService->issueOrChallenge($user, $request->deviceName(), $request)
        );
    }

    public function me(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokenService->revokeCurrent($request->user());

        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->tokenService->revokeAll($request->user());

        return response()->json(['message' => 'Logged out of all devices']);
    }
}
