<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use Illuminate\Support\Collection;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function getForUser(User $user): Collection
    {
        return $user->activeConversations()
            ->with([
                'participants.user:id,name,tag,avatar',
                'lastMessage:id,content,created_at'
            ])
            ->latest('updated_at')
            ->get();
    }

    public function findExistingPrivate(User $initiator, User $other): ?Conversation
    {
        return Conversation::where('conversation_type', 'private')
            ->whereHas('participants', fn($q) => $q->where('user_id', $initiator->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $other->id))
            ->has('participants', '=', 2)
            ->with('lastMessage:id,content,created_at')
            ->first();
    }

    public function create(array $data): Conversation
    {
        return Conversation::create($data);
    }

    public function updateLastMessage(Conversation $conversation, ?string $messageId): void
    {
        $conversation->update(['last_message_id' => $messageId]);
    }

    public function delete(Conversation $conversation): void
    {
        $conversation->delete();
    }
}
