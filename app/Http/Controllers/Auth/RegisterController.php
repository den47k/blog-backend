<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterProfileRequest;
use App\Http\Requests\Auth\RegisterStartRequest;
use App\Http\Requests\Auth\RegisterVerifyEmailRequest;
use App\Http\Resources\User\AuthenticatedUserResource;
use App\Services\Auth\DeviceService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly DeviceService $deviceService,
    ) {}

    public function start(RegisterStartRequest $request)
    {
        $result = $this->registrationService->start(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
        );

        return response()->json([
            'registration_token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
            'email' => $result['user']->email,
        ], 201);
    }

    public function verifyEmail(RegisterVerifyEmailRequest $request)
    {
        $this->registrationService->verifyEmail($request->user(), $request->validated('otp'));

        return response()->json(['status' => 'pending_profile']);
    }

    public function resendOtp(Request $request)
    {
        $this->registrationService->resendOtp($request->user(), $request->ip());

        return response()->json(['message' => 'Verification code resent.']);
    }

    public function completeProfile(RegisterProfileRequest $request)
    {
        $user = $this->registrationService->completeProfile(
            $request->user(),
            $request->validated('name'),
            $request->validated('tag'),
        );

        $issued = $this->deviceService->issueForUser(
            $user,
            $request->validated('device_name'),
            $request,
        );

        return response()->json([
            'user' => new AuthenticatedUserResource($user),
            'token' => $issued['token'],
            'token_type' => 'Bearer',
            'device_id' => $issued['device']->id,
        ], 201);
    }

    public function suggestTag(Request $request)
    {
        return response()->json([
            'suggestions' => $this->registrationService->suggestTags($request->user()),
        ]);
    }
}
