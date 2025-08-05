<?php

namespace App\Http\Resources;

use App\Models\Participant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Redis;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $isGroup = $this->conversation_type === 'group';
        $otherParticipant = $this->getOtherParticipant($currentUser);
        $lastReadAt = $this->getLastReadAt($currentUser, $this->id);

        return [
            'id' => $this->id,
            'userTag' => $this->when(!$isGroup, $otherParticipant?->user->tag),
            'title' => $isGroup
                ? $this->title
                : $otherParticipant?->user->name ?? 'Unknown User',
            'description' => $this->when($isGroup, $this->description),
            // 'lastMessage' => $this->getLastMessageContent(),
            // 'lastMessageTimestamp' => $this->getLastMessageTimestamp(),
            'lastMessage' => new MessageResource($this->whenLoaded('lastMessage')),
            'hasUnread' => $this->hasUnreadMessages($lastReadAt),
            'avatar' => '', // ToDo: Implement avatar logic
            'type' => $this->conversation_type,
            'participants' => UserResource::collection(
                $this->participants->loadMissing('user')->pluck('user')
            ),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
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
        if ($this->relationLoaded('lastMessage') && $this->lastMessage) {
            return $this->lastMessage->created_at->toIso8601String();
        }
        return $this->updated_at->toIso8601String();
    }

    protected function getLastReadAt(User $user, string $conversationId): ?Carbon
    {
        $timestamp = Redis::hget("user:{$user->id}:last_read", "conversation:{$conversationId}");
        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    protected function hasUnreadMessages(?Carbon $lastReadAt): bool
    {
        return $lastReadAt
            ? $this->updated_at->gt($lastReadAt)
            : $this->last_message_id !== null;
    }
}
