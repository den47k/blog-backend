<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

class AttachmentsService
{
    protected $disk;
    protected $imageManager;

    public function __construct()
    {
        $this->disk = Storage::disk('s3');
        $this->imageManager = new ImageManager(new Driver());
    }

    public function storeAttachment(UploadedFile $file, string $conversationId, string $messageId)
    {
        $path = "attachments/{$conversationId}/{$messageId}";

        $fileName = $file->hashName();
        $filePath = $this->disk->putFileAs(
            "{$path}/original",
            $file,
            $fileName
        );

        $result = [
            'original' => $filePath,
            'type' => $this->getFileType($file),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName()
        ];

        if (in_array($result['type'], ['image', 'video'])) {
            $thumbnailPath = $this->generateThumbnail($file, $path);
            $result['thumbnail'] = $thumbnailPath;
        }

        return $result;
    }

    protected function getFileType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf' => 'document',
            str_contains($mime, 'msword') || str_contains($mime, 'officedocument.wordprocessingml') => 'document',
            str_contains($mime, 'spreadsheetml') => 'spreadsheet',
            str_contains($mime, 'presentationml') => 'presentation',
            str_contains($mime, 'zip') || str_contains($mime, 'rar') || str_contains($mime, 'tar') => 'archive',
            str_starts_with($mime, 'text/') => 'text',
            default => 'file'
        };
    }

    protected function generateThumbnail(UploadedFile $file, string $path)
    {
        $thumbnail = $this->imageManager->read($file->getRealPath())
            ->resize(300, 300)
            ->toJpeg();

        $thumbPath = "{$path}/thumb.jpg";
        $this->disk->put($thumbPath, $thumbnail->toString());

        return $thumbPath;
    }
}
