<?php

namespace App\Repositories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Collection;

class DeviceRepository
{
    public function findForUser(User $user, string $id): ?Device
    {
        return Device::where('user_id', $user->id)->where('id', $id)->first();
    }

    public function forToken(int $tokenId): ?Device
    {
        return Device::where('personal_access_token_id', $tokenId)->first();
    }

    public function listForUser(User $user): Collection
    {
        return Device::where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->get();
    }

    public function create(array $data): Device
    {
        return Device::create($data);
    }

    public function update(Device $device, array $data): Device
    {
        $device->update($data);

        return $device->refresh();
    }

    public function delete(Device $device): void
    {
        $device->delete();
    }
}
