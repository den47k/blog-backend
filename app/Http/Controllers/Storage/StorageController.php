<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    private const INLINE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __invoke(Request $request, string $path): StreamedResponse
    {
        $this->ensureServablePath($path);

        $disk = Storage::disk('s3');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $this->disposition(
                in_array($mimeType, self::INLINE_MIME_TYPES, true)
                    ? HeaderUtils::DISPOSITION_INLINE
                    : HeaderUtils::DISPOSITION_ATTACHMENT,
                $request->query('name'),
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function disposition(string $type, mixed $name): string
    {
        if (! is_string($name) || $name === '') {
            return $type;
        }

        $name = str_replace(['/', '\\'], '_', $name);
        $fallback = preg_replace('/[^\x20-\x7E]|[%"]/', '_', Str::ascii($name));

        return HeaderUtils::makeDisposition($type, $name, $fallback);
    }

    private function ensureServablePath(string $path): void
    {
        $segments = explode('/', $path);

        if (array_intersect($segments, ['', '.', '..']) !== []) {
            abort(404);
        }

        if ($segments[0] === 'avatars') {
            return;
        }

        if ($segments[0] !== 'attachments' || ! Str::isUuid($segments[1] ?? '')) {
            abort(404);
        }
    }
}
