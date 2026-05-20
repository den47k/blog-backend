<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        return [
            'id' => $this->id,
            'device_name' => $this->device_name,
            'client_type' => $this->client_type,
            'platform' => $this->platform,
            'browser' => $this->browser,
            'ip_address' => $this->ip_address,
            'last_seen_at' => $this->last_seen_at,
            'last_seen_ip' => $this->last_seen_ip,
            'is_current' => $this->personal_access_token_id === $currentTokenId,
            'trusted_at' => $this->trusted_at,
            'created_at' => $this->created_at,
        ];
    }
}
