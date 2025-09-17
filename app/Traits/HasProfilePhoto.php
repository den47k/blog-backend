<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

trait HasProfilePhoto
{
    public function updateAvatar(?UploadedFile $file): ?array
    {
        if (!$file) return null;

        $this->deleteOldAvatar();

        $newPaths = $this->storeAvatar($file, $this->id);

        $this->avatar = $newPaths;
        $this->save();

        return $newPaths;
    }

    public function storeAvatar(UploadedFile $file, string $userId): array
    {
        $disk = Storage::disk('s3');
        $imageManager = new ImageManager(new Driver());
        $path = "avatars/{$userId}";

        $originalPath = $disk->putFile($path, $file, [
            'visibility' => 'public',
            'name' => "original." . $file->extension()
        ]);

        $medium = $imageManager->read($file->getRealPath())
            ->resize(300, 300)
            ->toJpeg();
        $disk->put("{$path}/medium.jpg", $medium->toString());

        $small = $imageManager->read($file->getRealPath())
            ->resize(100, 100)
            ->toJpeg();
        $disk->put("{$path}/small.jpg", $small->toString());

        return [
            'original' => $originalPath,
            'medium' => "{$path}/medium.jpg",
            'small' => "{$path}/small.jpg"
        ];
    }

    public function deleteOldAvatar(): void
    {
        if (empty($this->avatar)) {
            return;
        }

        try {
            $disk = Storage::disk('s3');
            $disk->delete(array_values($this->avatar));
        } catch (\Exception $e) {
            \Log::error("Failed to delete old avatar for user {$this->id}: " . $e->getMessage());
        }

        $this->avatar = null;
        $this->save();
    }

    public function getAvatarUrls(): ?array
    {
        if (empty($this->avatar)) return null;

        return [
            'original' => route('api.storage', ['path' => $this->avatar['original']]),
            'medium' => route('api.storage', ['path' => $this->avatar['medium']]),
            'small' => route('api.storage', ['path' => $this->avatar['small']])
        ];
    }
}
