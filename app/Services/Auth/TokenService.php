<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;

class TokenService
{
    public function issueToken(User $user, string $deviceName, array $abilities = ['*'], ?Carbon $expiresAt = null): string
    {
        return $user->createToken($deviceName, $abilities, $expiresAt)->plainTextToken;
    }

    public function revokeCurrent(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
    }

    public function revokeAllExcept(User $user, ?int $keepTokenId): void
    {
        $query = $user->tokens();

        if ($keepTokenId !== null) {
            $query->where('id', '!=', $keepTokenId);
        }

        $query->delete();
    }
}
