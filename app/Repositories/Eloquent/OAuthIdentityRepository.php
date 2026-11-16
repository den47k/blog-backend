<?php

namespace App\Repositories\Eloquent;

use App\Models\OAuthIdentity;
use App\Repositories\Interfaces\OAuthIdentityRepositoryInterface;

class OAuthIdentityRepository implements OAuthIdentityRepositoryInterface
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
