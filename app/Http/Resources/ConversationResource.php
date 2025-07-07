<?php

namespace App\Http\Resources;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $isGroup = $this->conversation_type === 'group';

        return [
            'id' => $this->id,
            'userTag' => $this->when(!$isGroup, $this->getOtherParticipant($currentUser)?->user->tag),
            'title' => $isGroup
                ? $this->title
                : $this->getOtherParticipant($currentUser)?->user->name ?? 'Unknown User',
            'description' => $this->when($isGroup, $this->description),
            'lastMessage' => $this->getLastMessageContent(),
            'timestamp' => $this->getLastMessageTimestamp(),
            'unread' => 0, // Placeholder for future implementation
            'avatar' => '',
            'online' => null,
            'isGroup' => $isGroup,
            'createdAt' => (string) $this->created_at,
            'updatedAt' => (string) $this->updated_at,
        ];
    }

    protected function getOtherParticipant(User $currentUser): ?Participant
    {
        return $this->participants
            ->where('user_id', '!=', $currentUser->id)
            ->first();
    }

    protected function getLastMessageContent(): string
    {
        return $this->relationLoaded('lastMessage')
            ? $this->lastMessage->content ?? ''
            : '';
    }

    protected function getLastMessageTimestamp(): string
    {
        return $this->relationLoaded('lastMessage')
            ? (string) ($this->lastMessage->created_at ?? $this->updated_at)
            : (string) $this->updated_at;
    }
}
