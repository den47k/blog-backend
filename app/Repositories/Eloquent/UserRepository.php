<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function findOrFail(string $id): User
    {
        return User::findOrFail($id);
    }

    public function findByTag(string $tag): User
    {
        return User::where('tag', $tag)->firstOrFail();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findManyByIds(array $ids): Collection
    {
        return User::whereIn('id', $ids)->get();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function search(string $query, string $excludeUserId, int $limit = 10): Collection
    {
        return User::where('id', '!=', $excludeUserId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('tag', 'ILIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();
    }
}
