<?php

namespace App\Traits\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

trait HasProfilePhoto
{
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
}
