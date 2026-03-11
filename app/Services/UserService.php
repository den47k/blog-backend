<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AvatarService $avatarService,
    ) {
    }

    public function updateProfile(User $user, ?string $name, ?UploadedFile $avatar): array
    {
        $response = [];

        if ($name !== null) {
            $user->name = $name;
            $user->save();
            $response['name'] = $user->name;
        }

        if ($avatar !== null) {
            $user->updateAvatar($avatar);
            $response['avatar'] = $this->avatarService->getUrls($user->avatar);
        }

        return $response;
    }

    public function deleteAvatar(User $user): void
    {
        $user->deleteOldAvatar();
    }

    public function searchUsers(string $query, string $excludeUserId): Collection
    {
        return $this->userRepository->search($query, $excludeUserId);
    }
}
