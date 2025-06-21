<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->conversation_type,
            'last_message' => $this->whenLoaded('lastMessage', fn() => [
                'id' => $this->lastMessage->id,
                'body' => $this->lastMessage->body,
                'created_at' => $this->lastMessage->created_at,
            ]),
            'other_user' => $this->other_participant ? [
                'id' => $this->other_participant->user->id,
                'name' => $this->other_participant->user->name,
                'tag' => $this->other_participant->user->tag,
            ] : null,
            'updated_at' => $this->updated_at,
        ];
    }
}
