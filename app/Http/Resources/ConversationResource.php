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
            'userTag' => $this->conversation_type === 'private' ? $this->tag : null,
            'title' => $this->conversation_type === 'group' 
                ? $this->title 
                : (optional($this->other_participant)->user->name ?? ''),
            'description' => $this->conversation_type === 'group' ? $this->description : null,
            'lastMessage' => $this->whenLoaded('lastMessage', function () {
                return $this->lastMessage->content ?? '';
            }, ''),
            'timestamp' => $this->whenLoaded('lastMessage', function () {
                return $this->lastMessage 
                    ? (string) $this->lastMessage->created_at 
                    : (string) $this->updated_at;
            }, (string) $this->updated_at),
            'unread' => 0, // Default until implemented
            'avatar' => '', // Default until implemented
            'online' => null, // Default until implemented
            'isGroup' => $this->conversation_type === 'group',
            'createdAt' => (string) $this->created_at,
            'updatedAt' => (string) $this->updated_at,
        ];
    }
}
