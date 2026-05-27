<?php

namespace App\Http\Resources\Message;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'conversationId' => $this->conversation_id,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
            'sender' => new UserResource($this->whenLoaded('user')),
            'attachment' => new MessageAttachmentResource(
                $this->whenLoaded('attachment'),
            ),
        ];
    }
}
