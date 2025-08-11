<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use App\Services\ConversationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $isGroup = $this->conversation_type === 'group';
        $otherParticipant = $this->getOtherParticipant($currentUser);

        return [
            'id' => $this->id,
            'userTag' => $this->when(!$isGroup, $otherParticipant?->user->tag),
            'title' => $isGroup
                ? $this->title
                : $otherParticipant?->user->name ?? 'Unknown User',
            'description' => $this->when($isGroup, $this->description),
            'lastMessage' => new MessageResource($this->whenLoaded('lastMessage')),
            'hasUnread' => $this->hasUnreadMessages($currentUser, $this->resource),
            'avatar' => $this->getAvatar($isGroup, $otherParticipant),
            'type' => $this->conversation_type,
            'participants' => UserResource::collection(
                $this->participants->loadMissing('user')->pluck('user')
            ),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }

    protected function getAvatar(bool $isGroup, ?Participant $otherParticipant): ?array
    {
        if ($isGroup) {
            if ($this->avatar) {
                return [
                    'original' => route('api.storage', ['path' => $this->avatar['original']]),
                    'medium'   => route('api.storage', ['path' => $this->avatar['medium']]),
                    'small'    => route('api.storage', ['path' => $this->avatar['small']]),
                ];
            }

            return null;
        }

        if ($otherParticipant && $otherParticipant->user->avatar) {
            return [
                'original' => route('api.storage', ['path' => $otherParticipant->user->avatar['original']]),
                'medium'   => route('api.storage', ['path' => $otherParticipant->user->avatar['medium']]),
                'small'    => route('api.storage', ['path' => $otherParticipant->user->avatar['small']]),
            ];
        }

        return null;
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

    protected function hasUnreadMessages(User $user, Conversation $conversation): bool
    {
        $service = app(ConversationService::class);
        $lastReadAt = $service->getLastReadAt($user, $conversation);

        return $lastReadAt
            ? $this->updated_at->gt($lastReadAt)
            : $this->last_message_id !== null;
    }
}
