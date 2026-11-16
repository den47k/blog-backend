<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Http\Requests\Auth\TwoFactorVerifyRequest;
use App\Http\Responses\AuthResponse;
use App\Services\Auth\DeviceService;
use App\Services\Auth\TokenService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly DeviceService $deviceService,
        private readonly TokenService $tokenService,
    ) {}

    public function enable(Request $request): JsonResponse
    {
        return response()->json(
            $this->twoFactorService->startEnrollment($request->user())
        );
    }

    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        return response()->json(
            $this->twoFactorService->confirmEnrollment($request->user(), $request->validated('otp'))
        );
    }

    public function verify(TwoFactorVerifyRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->twoFactorService->verifyForLogin(
            $user,
            $request->validated('code'),
            $request->validated('recovery_code'),
        );

        $this->tokenService->revokeCurrent($user);

        return AuthResponse::from($this->deviceService->issueForUser(
            $user,
            $request->validated('device_name'),
            $request,
        ));
    }

    public function disable(ConfirmPasswordRequest $request): JsonResponse
    {
        $this->twoFactorService->disable($request->user(), $request->validated('password'));

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    public function regenerateRecoveryCodes(ConfirmPasswordRequest $request): JsonResponse
    {
        $codes = $this->twoFactorService->regenerateRecoveryCodes(
            $request->user(),
            $request->validated('password'),
        );

        return response()->json(['recovery_codes' => $codes]);
    }
}
