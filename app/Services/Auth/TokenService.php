<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    public function createToken(User $user, string $name, array $abilities = ['*'], ?Carbon $expiresAt = null): NewAccessToken
    {
        return $user->createToken($name, $abilities, $expiresAt);
    }

    public function issueToken(User $user, string $deviceName, array $abilities = ['*'], ?Carbon $expiresAt = null): string
    {
        return $this->createToken($user, $deviceName, $abilities, $expiresAt)->plainTextToken;
    }

    public function revokeById(int $tokenId): void
    {
        PersonalAccessToken::whereKey($tokenId)->delete();
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
