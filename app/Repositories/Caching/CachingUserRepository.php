<?php

namespace App\Repositories\Caching;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Support\Cache\CacheHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CachingUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly UserRepositoryInterface $inner) {}

    public function findOrFail(string $id): User
    {
        return CacheHelper::rememberWithLock(
            CacheHelper::userProfile($id),
            CacheHelper::TTL_USER_PROFILE,
            fn () => $this->inner->findOrFail($id),
        );
    }

    public function findByTag(string $tag): User
    {
        return $this->inner->findByTag($tag);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->inner->findByEmail($email);
    }

    public function findByEmailAnyStatus(string $email): ?User
    {
        return $this->inner->findByEmailAnyStatus($email);
    }

    public function findManyByIds(array $ids): Collection
    {
        return $this->inner->findManyByIds($ids);
    }

    public function create(array $data): User
    {
        return $this->inner->create($data);
    }

    public function update(User $user, array $data): User
    {
        $updated = $this->inner->update($user, $data);
        Cache::forget(CacheHelper::userProfile($updated->id));

        return $updated;
    }

    public function markEmailVerified(User $user): User
    {
        $updated = $this->inner->markEmailVerified($user);
        Cache::forget(CacheHelper::userProfile($updated->id));

        return $updated;
    }

    public function search(string $query, string $excludeUserId, int $limit = 10): Collection
    {
        return $this->inner->search($query, $excludeUserId, $limit);
    }
}
