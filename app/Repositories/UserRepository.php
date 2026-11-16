<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;

class UserRepository
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

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function markEmailVerified(User $user): User
    {
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function markPasswordChanged(User $user): void
    {
        $user->forceFill(['password_changed_at' => now()])->save();
    }

    public function setAvatar(User $user, ?array $paths): void
    {
        $user->forceFill(['avatar' => $paths])->save();
    }

    public function tagExists(string $tag): bool
    {
        return User::where('tag', $tag)->exists();
    }

    /** Ids of users still in the given registration status since before the cutoff. */
    public function pendingOlderThan(UserStatus $status, DateTimeInterface $cutoff): Collection
    {
        return User::where('status', $status)
            ->where('created_at', '<', $cutoff)
            ->pluck('id');
    }

    /**
     * @param  iterable<string>  $ids
     */
    public function deleteMany(iterable $ids): int
    {
        return User::whereIn('id', $ids)->delete();
    }

    public function search(string $query, string $excludeUserId, int $limit = 10): Collection
    {
        $pattern = '%'.addcslashes($query, '%_\\').'%';

        return User::where('id', '!=', $excludeUserId)
            ->where('status', UserStatus::Active)
            ->where(function ($q) use ($pattern) {
                $q->where('name', 'ILIKE', $pattern)
                    ->orWhere('tag', 'ILIKE', $pattern);
            })
            ->limit($limit)
            ->get();
    }
}
