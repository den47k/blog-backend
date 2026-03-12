<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesAvatarUrls;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    use ResolvesAvatarUrls;
    protected User $currentUser;
    protected static array $unreadMap = [];

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->currentUser = auth()->user();
    }

    public static function withUnreadMap(array $map): void
    {
        static::$unreadMap = $map;
    }

    public static function forUser($resource, User $user): self
    {
        $instance = new self($resource);
        $instance->currentUser = $user;
        return $instance;
    }

    public function toArray(Request $request): array
    {
        $otherParticipant = $this->getOtherParticipant();
        $isGroup = $this->isGroupConversation();

        return [
            'id' => $this->id,
            'userTag' => $this->when(!$isGroup, $otherParticipant?->user->tag),
            'title' => $this->getConversationTitle($isGroup, $otherParticipant),
            'description' => $this->when($isGroup, $this->description),
            'lastMessage' => new MessageResource(
                $this->whenLoaded('lastMessage'),
            ),
            'hasUnread' => static::$unreadMap[$this->id] ?? false,
            'avatar' => $this->getAvatar($isGroup, $otherParticipant),
            'type' => $this->conversation_type,
            'participants' => UserResource::collection(
                $this->getParticipants(),
            ),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }

    private function isGroupConversation(): bool
    {
        return $this->conversation_type === 'group';
    }

    private function getConversationTitle(
        bool $isGroup,
        ?Participant $otherParticipant,
    ): string {
        return $isGroup
            ? $this->title
            : $otherParticipant?->user->name ?? 'Unknown User';
    }

    private function getParticipants()
    {
        return $this->participants->loadMissing('user')->pluck('user');
    }

    private function getAvatar(
        bool $isGroup,
        ?Participant $otherParticipant,
    ): ?array {
        if ($isGroup) {
            return $this->getGroupAvatar();
        }

        return $this->getUserAvatar($otherParticipant);
    }

    private function getGroupAvatar(): ?array
    {
        return $this->resolveAvatarUrls($this->avatar);
    }

    private function getUserAvatar(?Participant $participant): ?array
    {
        return $this->resolveAvatarUrls($participant?->user->avatar);
    }

    private function getOtherParticipant(): ?Participant
    {
        return $this->participants
            ->where('user_id', '!=', $this->currentUser->id)
            ->first();
    }
}
