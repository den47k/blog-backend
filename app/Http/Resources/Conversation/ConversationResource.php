<?php

namespace App\Http\Resources\Conversation;

use App\Http\Resources\Concerns\ResolvesAvatarUrls;
use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\User\UserResource;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    use ResolvesAvatarUrls;

    public function __construct(
        $resource,
        private readonly User $viewer,
        private readonly ?bool $hasUnread = null,
    ) {
        parent::__construct($resource);
    }

    public static function collectionFor(CursorPaginator $conversations, User $viewer): AnonymousResourceCollection
    {
        return new AnonymousResourceCollection(
            $conversations->through(fn ($conversation) => new self($conversation, $viewer)),
            self::class,
        );
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
            'hasUnread' => $this->hasUnread ?? (bool) $this->resource->getAttribute('has_unread'),
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
            ->where('user_id', '!=', $this->viewer->id)
            ->first();
    }
}
