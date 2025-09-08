<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use App\Repositories\ConversationRedisRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->redisRepository = app(ConversationRedisRepository::class);
    }

    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $isGroup = $this->isGroupConversation();
        $otherParticipant = $this->getOtherParticipant($currentUser);

        return [
            'id' => $this->id,
            'userTag' => $this->when(!$isGroup, $otherParticipant?->user->tag),
            'title' => $this->getConversationTitle($isGroup, $otherParticipant),
            'description' => $this->when($isGroup, $this->description),
            'lastMessage' => new MessageResource($this->whenLoaded('lastMessage')),
            'hasUnread' => $this->hasUnreadMessages($currentUser),
            'avatar' => $this->getAvatar($isGroup, $otherParticipant),
            'type' => $this->conversation_type,
            'participants' => UserResource::collection($this->getParticipants()),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }

    private function isGroupConversation(): bool
    {
        return $this->conversation_type === 'group';
    }

    private function getConversationTitle(bool $isGroup, ?Participant $otherParticipant): string
    {
        return $isGroup ? $this->title : $otherParticipant?->user->name ?? 'Unknown User';
    }

    private function getParticipants()
    {
        return $this->participants->loadMissing('user')->pluck('user');
    }

    private function getAvatar(bool $isGroup, ?Participant $otherParticipant): ?array
    {
        if ($isGroup) {
            return $this->getGroupAvatar();
        }

        return $this->getUserAvatar($otherParticipant);
    }

    private function getGroupAvatar(): ?array
    {
        if (!$this->avatar) {
            return null;
        }

        return [
            'original' => $this->getAvatarUrl($this->avatar['original']),
            'medium' => $this->getAvatarUrl($this->avatar['medium']),
            'small' => $this->getAvatarUrl($this->avatar['small']),
        ];
    }

    private function getUserAvatar(?Participant $participant): ?array
    {
        if (!$participant?->user->avatar) {
            return null;
        }

        return [
            'original' => $this->getAvatarUrl($participant->user->avatar['original']),
            'medium' => $this->getAvatarUrl($participant->user->avatar['medium']),
            'small' => $this->getAvatarUrl($participant->user->avatar['small']),
        ];
    }

    private function getAvatarUrl(string $path): string
    {
        return route('api.storage', ['path' => $path]);
    }

    private function getOtherParticipant(User $currentUser): ?Participant
    {
        return $this->participants
            ->where('user_id', '!=', $currentUser->id)
            ->first();
    }

    private function hasUnreadMessages(User $user): bool
    {
        $lastReadAt = $this->redisRepository->getLastReadAt($user, $this->resource);

        if ($lastReadAt) {
            return $this->updated_at->gt($lastReadAt);
        }

        return $this->last_message_id !== null;
    }
}
