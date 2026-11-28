<?php

namespace App\Services\Storage;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class StorageUrlGenerator
{
    public function url(string $path): string
    {
        return url(URL::temporarySignedRoute(
            'api.storage',
            $this->expiresAt(),
            ['path' => $path],
            absolute: false,
        ));
    }

    private function expiresAt(): Carbon
    {
        return Carbon::now('UTC')->addDays(2)->startOfDay();
    }
}
