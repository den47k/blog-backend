<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AvatarService $avatarService,
    ) {}

    public function updateProfile(User $user, ?string $name, ?UploadedFile $avatar): array
    {
        $response = [];

        if ($name !== null) {
            $this->userRepository->update($user, ['name' => $name]);
            $response['name'] = $user->name;
        }

        if ($avatar !== null) {
            $this->replaceAvatar($user, $avatar);
            $response['avatar'] = $this->avatarService->getUrls($user->avatar);
        }

        return $response;
    }

    public function deleteAvatar(User $user): void
    {
        $this->removeAvatar($user);
    }

    public function searchUsers(string $query, string $excludeUserId): Collection
    {
        return $this->userRepository->search($query, $excludeUserId);
    }

    private function replaceAvatar(User $user, UploadedFile $file): void
    {
        $this->removeAvatar($user);

        $this->userRepository->setAvatar($user, $this->avatarService->store($file, $user->id));
    }

    private function removeAvatar(User $user): void
    {
        if (empty($user->avatar)) {
            return;
        }

        $this->avatarService->delete($user->avatar);
        $this->userRepository->setAvatar($user, null);
    }
}
