<?php

namespace App\Http\Resources\Concerns;

trait ResolvesAvatarUrls
{
    private function resolveAvatarUrls(?array $avatar): ?array
    {
        if (!$avatar) {
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
        return route('api.storage', ['path' => $path]);
    }
}
