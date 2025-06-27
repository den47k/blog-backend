<?php

namespace App\Services;

use App\Events\EmailVerified;
use App\Http\Requests\ResendVerificationRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerificationService
{
    public function sendVerificationEmail(User $user)
    {
        $user->sendEmailVerificationNotification();
    }

    public function verify(Request $request)
    {
        $user = User::findOrFail($request->route('id'));

        if (!hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return false;
        }

        if ($user->hasVerifiedEmail()) {
            return $user;
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            event(new EmailVerified($user));
        }

        return new UserResource($user);
    }

    public function resendVerification(ResendVerificationRequest $request)
    {
        $user = User::where('email', $request->validated()['email'])->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            abort(400, 'Email already verified');
        }

        $user->sendEmailVerificationNotification();
        return new UserResource($user);
    }
}
