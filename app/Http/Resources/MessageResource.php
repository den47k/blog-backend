<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'conversationId' => $this->conversation_id,
            'senderId' => $this->user_id,
            'createdAt' => $this->created_at->toISOString(),
            'sender' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'tag' => $this->user->tag,
                'avatar' => $this->user->avatar,
            ]),
        ];
    }
}
