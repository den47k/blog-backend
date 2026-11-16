<?php

namespace App\Repositories\Interfaces;

use App\Models\OAuthIdentity;

interface OAuthIdentityRepositoryInterface
{
    public function findByProviderUid(string $provider, string $providerUid): ?OAuthIdentity;

    public function create(array $data): OAuthIdentity;

    public function delete(OAuthIdentity $identity): void;
}
