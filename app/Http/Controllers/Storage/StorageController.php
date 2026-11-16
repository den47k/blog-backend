<?php

namespace App\Http\Controllers\Storage;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    /** Types a browser may render from the API origin; everything else is downloaded. */
    private const INLINE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __invoke(string $path): StreamedResponse
    {
        $this->authorizePath($path);

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
            'Content-Disposition' => in_array($mimeType, self::INLINE_MIME_TYPES, true) ? 'inline' : 'attachment',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Avatars are readable by any signed-in user, attachments only by
     * participants of the conversation named in the path.
     */
    private function authorizePath(string $path): void
    {
        $segments = explode('/', $path);

        // The disk would resolve "a/../b" to "b", past the check below.
        if (array_intersect($segments, ['', '.', '..']) !== []) {
            abort(404);
        }

        if ($segments[0] === 'avatars') {
            return;
        }

        if ($segments[0] !== 'attachments' || ! Str::isUuid($segments[1] ?? '')) {
            abort(404);
        }

        Gate::authorize('view', Conversation::findOrFail($segments[1]));
    }
}
