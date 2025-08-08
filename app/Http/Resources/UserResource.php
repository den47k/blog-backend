<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
                'original' => route('api.storage', ['path' => $this->avatar['original']]),
                'medium' => route('api.storage', ['path' => $this->avatar['medium']]),
                'small' => route('api.storage', ['path' => $this->avatar['small']])
            ] : null,
            // 'avatar' => $this->avatar ? [
            //     'original' => Storage::temporaryUrl($this->avatar['original'], now()->addMinutes(15)),
            //     'medium' => Storage::temporaryUrl($this->avatar['medium'], now()->addMinutes(15)),
            //     'small' => Storage::temporaryUrl($this->avatar['small'], now()->addMinutes(15)),
            // ] : null,

            // 'avatar' => $this->avatar ? [
            //     'original' => Storage::disk('s3')->temporaryUrl($this->avatar['original'], now()->addMinutes(15)),
            //     'medium' => Storage::disk('s3')->temporaryUrl($this->avatar['medium'], now()->addMinutes(15)),
            //     'small' => Storage::disk('s3')->temporaryUrl($this->avatar['small'], now()->addMinutes(15)),
            // ] : null,
            'isEmailVerified' => (bool) $this->email_verified_at,
        ];
    }
}
