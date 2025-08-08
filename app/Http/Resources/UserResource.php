<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tag' => $this->tag,
            'email' => $this->email,
            'avatar' => $this->avatar ? [
                'original' => $this->publicAvatarUrl($this->avatar['original']),
                'medium'   => $this->publicAvatarUrl($this->avatar['medium']),
                'small'    => $this->publicAvatarUrl($this->avatar['small']),
            ] : null,
            'isEmailVerified' => (bool) $this->email_verified_at,
        ];
    }

    private function publicAvatarUrl(string $path): string
    {
        $baseUrl = rtrim(Config::get('filesystems.disks.s3.url'), '/');
        return "{$baseUrl}/{$path}";
    }
}
