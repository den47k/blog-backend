<?php

namespace App\Repositories\Interfaces;

use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Support\Collection;

interface OAuthIdentityRepositoryInterface
{
    public function findByProviderUid(string $provider, string $providerUid): ?OAuthIdentity;

    public function forUser(User $user): Collection;

    public function create(array $data): OAuthIdentity;

    public function delete(OAuthIdentity $identity): void;
}
