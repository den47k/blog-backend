<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Eloquent\TokenRepository;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    public function __construct(
        private readonly TokenRepository $tokenRepository,
    ) {}

    public function createToken(User $user, string $name, array $abilities = ['*'], ?Carbon $expiresAt = null): NewAccessToken
    {
        return $user->createToken($name, $abilities, $expiresAt);
    }

    public function issueToken(User $user, string $deviceName, array $abilities = ['*'], ?Carbon $expiresAt = null): string
    {
        return $this->createToken($user, $deviceName, $abilities, $expiresAt)->plainTextToken;
    }

    public function revokeCurrent(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $this->tokenRepository->delete($token);
        }
    }

    public function revokeById(int $tokenId): void
    {
        $this->tokenRepository->deleteById($tokenId);
    }

    /**
     * @param  iterable<int>  $tokenIds
     */
    public function revokeMany(iterable $tokenIds): int
    {
        return $this->tokenRepository->deleteMany($tokenIds);
    }

    public function revokeAll(User $user): void
    {
        $this->tokenRepository->deleteForUser($user);
    }

    public function revokeAllExcept(User $user, ?int $keepTokenId): void
    {
        $this->tokenRepository->deleteForUser($user, $keepTokenId);
    }

    /**
     * @param  iterable<string>  $userIds
     */
    public function revokeAllForUsers(iterable $userIds): void
    {
        $this->tokenRepository->deleteForUsers($userIds);
    }
}
