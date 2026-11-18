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
            'deviceName' => $this->device_name,
            'clientType' => $this->client_type,
            'platform' => $this->platform,
            'browser' => $this->browser,
            'ipAddress' => $this->ip_address,
            'lastSeenAt' => $this->last_seen_at?->toISOString(),
            'lastSeenIp' => $this->last_seen_ip,
            'isCurrent' => $this->personal_access_token_id === $currentTokenId,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
