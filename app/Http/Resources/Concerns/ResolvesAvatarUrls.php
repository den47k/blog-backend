<?php

namespace App\Http\Resources\Concerns;

use App\Services\Storage\StorageUrlGenerator;

trait ResolvesAvatarUrls
{
    private function resolveAvatarUrls(?array $avatar): ?array
    {
        if (! $avatar) {
            return null;
        }

        return [
            'original' => $this->avatarUrl($avatar['original']),
            'medium' => $this->avatarUrl($avatar['medium']),
            'small' => $this->avatarUrl($avatar['small']),
        ];
    }

    private function avatarUrl(string $path): string
    {
        return app(StorageUrlGenerator::class)->url($path);
    }
}
