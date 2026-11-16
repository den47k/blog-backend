<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

class ConversationRepository
{
    public function getForUser(User $user, int $perPage = 20): CursorPaginator
    {
        return $user->activeConversations()
            ->with([
                'participants.user:id,name,tag,avatar',
                'lastMessage:id,content,created_at,updated_at',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findExistingPrivate(User $initiator, User $other): ?Conversation
    {
        return Conversation::where('conversation_type', 'private')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $initiator->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $other->id))
            ->has('participants', '=', 2)
            ->with('lastMessage:id,content,created_at,updated_at')
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
