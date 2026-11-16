<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class TokenRepository
{
    public function delete(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    public function deleteById(int $tokenId): void
    {
        PersonalAccessToken::whereKey($tokenId)->delete();
    }

    /**
     * @param  iterable<int>  $tokenIds
     */
    public function deleteMany(iterable $tokenIds): int
    {
        return PersonalAccessToken::whereIn('id', $tokenIds)->delete();
    }

    public function deleteForUser(User $user, ?int $exceptTokenId = null): void
    {
        $query = $user->tokens();

        if ($exceptTokenId !== null) {
            $query->where('id', '!=', $exceptTokenId);
        }

        $query->delete();
    }

    /**
     * @param  iterable<string>  $userIds
     */
    public function deleteForUsers(iterable $userIds): void
    {
        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->delete();
    }
}
