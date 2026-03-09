<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function findOrFail(string $id): User;

    public function findByTag(string $tag): User;

    public function findByEmail(string $email): ?User;

    public function findManyByIds(array $ids): Collection;

    public function create(array $data): User;
}
