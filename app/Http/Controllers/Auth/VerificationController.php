<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\ResendVerificationRequest;
use App\Services\VerificationService;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(
        private VerificationService $verificationService
    ) {}

    public function verify(Request $request)
    {
        $user = $this->verificationService->verify($request);

        if (!$user) {
            return response()->json(['message' => 'Invalid verification link'], 400);
        }

        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }

    public function resend(ResendVerificationRequest $request)
    {
        $user = $this->verificationService->resendVerification($request);

        return response()->json([
            'message' => 'Verification link resent',
            'user' => $user
        ]);
    }
}
