<?php

namespace App\Repositories\Interfaces;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Collection;

interface DeviceRepositoryInterface
{
    public function findById(string $id): ?Device;

    public function findForUser(User $user, string $id): ?Device;

    public function forToken(int $tokenId): ?Device;

    public function listForUser(User $user): Collection;

    public function create(array $data): Device;

    public function update(Device $device, array $data): Device;

    public function delete(Device $device): void;
}
