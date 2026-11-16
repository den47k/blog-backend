<?php

namespace App\Repositories;

use App\Models\OAuthIdentity;

class OAuthIdentityRepository
{
    public function findByProviderUid(string $provider, string $providerUid): ?OAuthIdentity
    {
        return OAuthIdentity::where('provider', $provider)
            ->where('provider_user_id', $providerUid)
            ->first();
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
