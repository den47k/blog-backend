<?php

namespace App\Services\Storage;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class StorageUrlGenerator
{
    public function url(string $path, ?string $downloadName = null): string
    {
        $parameters = ['path' => $path];
        if ($downloadName !== null && $downloadName !== '') {
            $parameters['name'] = $downloadName;
        }

        return url(URL::temporarySignedRoute(
            'api.storage',
            $this->expiresAt(),
            $parameters,
            absolute: false,
        ));
    }

    private function expiresAt(): Carbon
    {
        return Carbon::now('UTC')->addDays(2)->startOfDay();
    }
}
