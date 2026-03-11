<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class AvatarService
{
    protected $disk;
    protected $imageManager;

    public function __construct()
    {
        $this->disk = Storage::disk('s3');
        $this->imageManager = new ImageManager(new Driver());
    }

    public function store(UploadedFile $file, string $userId): array
    {
        $path = "avatars/{$userId}";

        $originalPath = $this->disk->putFile($path, $file, [
            'visibility' => 'public',
            'name' => "original." . $file->extension()
        ]);

        $medium = $this->imageManager->read($file->getRealPath())
            ->resize(300, 300)
            ->toJpeg();
        $this->disk->put("{$path}/medium.jpg", $medium->toString());

        $small = $this->imageManager->read($file->getRealPath())
            ->resize(100, 100)
            ->toJpeg();
        $this->disk->put("{$path}/small.jpg", $small->toString());

        return [
            'original' => $originalPath,
            'medium' => "{$path}/medium.jpg",
            'small' => "{$path}/small.jpg"
        ];
    }

    public function delete(array $avatarPaths): void
    {
        try {
            $this->disk->delete(array_values($avatarPaths));
        } catch (\Exception $e) {
            Log::error("Failed to delete avatar files: " . $e->getMessage());
        }
    }

    public function getUrls(array $avatarPaths): array
    {
        return [
            'original' => route('api.storage', ['path' => $avatarPaths['original']]),
            'medium' => route('api.storage', ['path' => $avatarPaths['medium']]),
            'small' => route('api.storage', ['path' => $avatarPaths['small']])
        ];
    }
}
