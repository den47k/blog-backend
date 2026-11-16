<?php

namespace App\Http\Resources\Conversation;

use App\Http\Resources\Concerns\ResolvesAvatarUrls;
use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\User\UserResource;
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
        $otherUser = $this->getOtherParticipant()?->user;

        return [
            'id' => $this->id,
            'userTag' => $otherUser?->tag,
            'title' => $otherUser?->name ?? 'Unknown User',
            'lastMessage' => new MessageResource(
                $this->whenLoaded('lastMessage'),
            ),
            'hasUnread' => static::$unreadMap[$this->id] ?? false,
            'avatar' => $this->resolveAvatarUrls($otherUser?->avatar),
            'type' => $this->conversation_type,
            'participants' => UserResource::collection(
                $this->participants->loadMissing('user')->pluck('user'),
            ),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }

    private function getOtherParticipant(): ?Participant
    {
        return $this->participants
            ->where('user_id', '!=', $this->currentUser->id)
            ->first();
    }
}
