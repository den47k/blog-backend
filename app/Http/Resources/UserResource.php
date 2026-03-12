<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesAvatarUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use ResolvesAvatarUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tag' => $this->tag,
            'email' => $this->email,
            'avatar' => $this->resolveAvatarUrls($this->avatar),
            'isEmailVerified' => (bool) $this->email_verified_at,
        ];
    }
}
