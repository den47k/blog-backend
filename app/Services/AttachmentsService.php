<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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

    public function storeForMessage(Message $message, UploadedFile $file): void
    {
        $path = "attachments/{$message->conversation_id}/{$message->id}";
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

        $message->attachment()->create(['file_data' => $fileData]);
    }


    public function deleteFiles(Collection $attachments): void
    {
        if ($attachments->isEmpty()) {
            return;
        }

        $attachment = $attachments->first();

        $pathsToDelete = [];

        if (!empty($attachment->file_data['original'])) {
            $pathsToDelete[] = $attachment->file_data['original'];
        }
        if (!empty($attachment->file_data['thumbnail'])) {
            $pathsToDelete[] = $attachment->file_data['thumbnail'];
        }

        if (!empty($pathsToDelete)) {
            try {
                $this->disk->delete($pathsToDelete);
            } catch (\Exception $e) {
                Log::error("Failed to delete attachment files from storage: " . $e->getMessage());
            }
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
            $thumbPath = "{$path}/thumb_" . $file->hashName() . '.jpg';
            $this->disk->put($thumbPath, $thumbnail->toString(), 'public');
            return $thumbPath;
        } catch (\Exception $e) {
            Log::error("Thumbnail generation failed: " . $e->getMessage());
            return null;
        }
    }
}
