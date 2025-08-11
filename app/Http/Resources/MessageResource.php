<?php

namespace App\Http\Resources;

use App\Models\User;
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
            'senderId' => $this->user_id,
            'editedAt' => $this->edited_at,
            'createdAt' => $this->created_at->toISOString(),
            'sender' => $this->whenLoaded('user', fn () => [  // ToDo: use UserResource
                'id' => $this->user->id,
                'name' => $this->user->name,
                'tag' => $this->user->tag,
                'avatar' => $this->user->avatar,
            ]),
            // 'attachments' => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
            'attachment' => new MessageAttachmentResource($this->whenLoaded('attachment')),
        ];
    }
}
