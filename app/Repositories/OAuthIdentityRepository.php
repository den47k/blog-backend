<?php

namespace App\Repositories;

use App\Models\OAuthIdentity;
use App\Models\User;

class OAuthIdentityRepository
{
    public function findByProviderUid(string $provider, string $providerUid): ?OAuthIdentity
    {
        return OAuthIdentity::where('provider', $provider)
            ->where('provider_user_id', $providerUid)
            ->first();
    }

    public function findForUser(User $user, string $id): ?OAuthIdentity
    {
        return OAuthIdentity::where('user_id', $user->id)
            ->where('id', $id)
            ->first();
    }

    public function countOthersForUser(User $user, string $exceptId): int
    {
        return OAuthIdentity::where('user_id', $user->id)
            ->where('id', '!=', $exceptId)
            ->count();
    }

    public function create(array $data): OAuthIdentity
    {
        return OAuthIdentity::create($data);
    }

    public function delete(OAuthIdentity $identity): void
    {
        $identity->delete();
    }
}
