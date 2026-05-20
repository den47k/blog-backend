<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\AuthenticatedUserResource;
use App\Services\Auth\DeviceService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly DeviceService $deviceService,
    ) {}

    public function enable(Request $request)
    {
        return response()->json(
            $this->twoFactorService->startEnrollment($request->user())
        );
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        return response()->json(
            $this->twoFactorService->confirmEnrollment($request->user(), $request->input('otp'))
        );
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'regex:/^\d{6}$/'],
            'recovery_code' => ['nullable', 'required_without:code', 'string'],
            'device_name' => ['required', 'string', 'max:128'],
        ]);

        $user = $request->user();

        $this->twoFactorService->verifyForLogin(
            $user,
            $request->input('code'),
            $request->input('recovery_code'),
        );

        $request->user()->currentAccessToken()?->delete();

        $issued = $this->deviceService->issueForUser(
            $user,
            $request->input('device_name'),
            $request,
        );

        return response()->json([
            'user' => new AuthenticatedUserResource($user),
            'token' => $issued['token'],
            'token_type' => 'Bearer',
            'device_id' => $issued['device']->id,
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $this->twoFactorService->disable($request->user(), $request->input('password'));

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate(['password' => ['required', 'string']]);

        $codes = $this->twoFactorService->regenerateRecoveryCodes(
            $request->user(),
            $request->input('password'),
        );

        return response()->json(['recovery_codes' => $codes]);
    }
}
