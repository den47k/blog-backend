<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorageController extends Controller
{
    public function __invoke(Request $request, string $path)
    {
        if (!Storage::disk('s3')->exists($path)) {
            abort(404);
        }

        return response()->stream(function () use ($path) {
            $stream = Storage::disk('s3')->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => Storage::disk('s3')->mimeType($path),
        ]);
    }
}
