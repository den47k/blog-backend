<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function findOrFail(string $id): User;

    public function findByTag(string $tag): User;

    public function findByEmail(string $email): ?User;

    public function findByEmailAnyStatus(string $email): ?User;

    public function findManyByIds(array $ids): Collection;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function markEmailVerified(User $user): User;

    public function search(string $query, string $excludeUserId, int $limit = 10): Collection;
}
