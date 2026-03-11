<?php

namespace App\Traits;

use App\Services\AvatarService;
use Illuminate\Http\UploadedFile;

trait HasProfilePhoto
{
    public function updateAvatar(UploadedFile $file): array
    {
        $avatarService = app(AvatarService::class);

        $this->deleteOldAvatar();

        $newPaths = $avatarService->store($file, $this->id);

        $this->avatar = $newPaths;
        $this->save();

        return $newPaths;
    }

    public function deleteOldAvatar(): void
    {
        if (empty($this->avatar)) {
            return;
        }

        app(AvatarService::class)->delete($this->avatar);

        $this->avatar = null;
        $this->save();
    }

    public function getAvatarUrls(): ?array
    {
        if (empty($this->avatar)) {
            return null;
        }

        return app(AvatarService::class)->getUrls($this->avatar);
    }
}
