<?php

namespace App\Repositories\Eloquent;

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

    public function find(string $id): ?Conversation
    {
        return Conversation::find($id);
    }

    public function findOrFail(string $id): Conversation
    {
        return Conversation::findOrFail($id);
    }

    /**
     * Takes a row lock for the rest of the transaction and brings the
     * given model up to date with the locked row.
     */
    public function lockForUpdate(Conversation $conversation): void
    {
        $locked = Conversation::whereKey($conversation->id)->lockForUpdate()->first();

        if ($locked) {
            $conversation->setRawAttributes($locked->getAttributes(), true);
        }
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
