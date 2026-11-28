<?php

namespace App\Services\User;

use App\Services\Storage\StorageUrlGenerator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class AvatarService
{
    private Filesystem $disk;

    private ImageManager $imageManager;

    public function __construct(
        private readonly StorageUrlGenerator $urls,
    ) {
        $this->disk = Storage::disk('s3');
        $this->imageManager = new ImageManager(new Driver);
    }

    public function store(UploadedFile $file, string $userId): array
    {
        $path = "avatars/{$userId}";

        $originalPath = $this->disk->putFile($path, $file);

        $medium = $this->imageManager->read($file->getRealPath())
            ->cover(300, 300)
            ->toJpeg();
        $this->disk->put("{$path}/medium.jpg", $medium->toString());

        $small = $this->imageManager->read($file->getRealPath())
            ->cover(100, 100)
            ->toJpeg();
        $this->disk->put("{$path}/small.jpg", $small->toString());

        return [
            'original' => $originalPath,
            'medium' => "{$path}/medium.jpg",
            'small' => "{$path}/small.jpg",
        ];
    }

    public function delete(array $avatarPaths): void
    {
        try {
            $this->disk->delete(array_values($avatarPaths));
        } catch (\Exception $e) {
            Log::error('Failed to delete avatar files: '.$e->getMessage());
        }
    }

    public function getUrls(array $avatarPaths): array
    {
        return [
            'original' => $this->urls->url($avatarPaths['original']),
            'medium' => $this->urls->url($avatarPaths['medium']),
            'small' => $this->urls->url($avatarPaths['small']),
        ];
    }
}
