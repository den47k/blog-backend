<?php

namespace App\Listeners\Auth;

use Illuminate\Auth\Events\PasswordReset;

class RevokeTokensOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        $user->forceFill(['password_changed_at' => now()])->save();
        $user->tokens()->delete();
    }
}
