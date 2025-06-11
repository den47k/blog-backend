<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResendVerificationRequest;
use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        Auth::login($user);

        return redirect(config('app.frontend_url'));
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
