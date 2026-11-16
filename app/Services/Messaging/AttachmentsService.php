<?php

namespace App\Services\Messaging;

use App\Models\MessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Uploads the file (and a thumbnail for images) and returns the
     * file_data to store on the attachment row.
     */
    public function upload(string $conversationId, string $messageId, UploadedFile $file): array
    {
        $path = "attachments/{$conversationId}/{$messageId}";
        $fileName = $file->hashName();

        $filePath = $this->disk->putFileAs($path, $file, $fileName, 'public');

        $fileData = [
            'original' => $filePath,
            'type' => $this->getFileType($file),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
        ];

        if ($fileData['type'] === 'image') {
            $fileData['thumbnail'] = $this->generateThumbnail($file, $path);
        }

        return $fileData;
    }

    public function deleteFile(MessageAttachment $attachment): void
    {
        $this->deletePaths($attachment->file_data);
    }

    public function deletePaths(array $fileData): void
    {
        $paths = array_values(array_filter([
            $fileData['original'] ?? null,
            $fileData['thumbnail'] ?? null,
        ]));

        if ($paths === []) {
            return;
        }

        try {
            $this->disk->delete($paths);
        } catch (\Exception $e) {
            Log::error('Failed to delete attachment files from storage: '.$e->getMessage());
        }
    }

    protected function getFileType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file'
        };
    }

    protected function generateThumbnail(UploadedFile $file, string $path): ?string
    {
        try {
            $thumbnail = $this->imageManager->read($file->getRealPath())
                ->resize(300, 300)
                ->toJpeg();
            $thumbPath = "{$path}/thumb_".$file->hashName().'.jpg';
            $this->disk->put($thumbPath, $thumbnail->toString(), 'public');

            return $thumbPath;
        } catch (\Exception $e) {
            Log::error('Thumbnail generation failed: '.$e->getMessage());

            return null;
        }
    }
}
