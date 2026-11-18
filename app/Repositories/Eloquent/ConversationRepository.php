<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ConversationRepository
{
    public function getForUser(User $user, int $perPage = 20): CursorPaginator
    {
        return $this->selectUnreadFor($user->activeConversations(), $user->id)
            ->with([
                'participants.user:id,name,tag,avatar',
                'lastMessage.user:id,name,tag,avatar',
                'lastMessage.attachment',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findExistingPrivate(User $initiator, User $other): ?Conversation
    {
        return $this->selectUnreadFor(Conversation::query(), $initiator->id)
            ->where('conversation_type', 'private')
            ->whereHas('participants', fn($q) => $q->where('user_id', $initiator->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $other->id))
            ->has('participants', '=', 2)
            ->with(['lastMessage.user:id,name,tag,avatar', 'lastMessage.attachment'])
            ->first();
    }

    public function hasUnread(Conversation $conversation, string $userId): bool
    {
        return Conversation::whereKey($conversation->id)
            ->whereExists($this->unreadFor($userId))
            ->exists();
    }

    public function find(string $id): ?Conversation
    {
        return Conversation::find($id);
    }

    public function findOrFail(string $id): Conversation
    {
        return Conversation::findOrFail($id);
    }

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

    private function selectUnreadFor($query, string $userId)
    {
        return $query
            ->select('conversations.*')
            ->selectRaw('exists (' . $this->unreadFor($userId)->toSql() . ') as has_unread', [$userId]);
    }

    private function unreadFor(string $userId): QueryBuilder
    {
        return DB::table('messages')
            ->join('participants as read_state', 'read_state.conversation_id', '=', 'messages.conversation_id')
            ->selectRaw('1')
            ->whereColumn('messages.id', 'conversations.last_message_id')
            ->where('read_state.user_id', $userId)
            ->where(fn(QueryBuilder $q) => $q
                ->whereNull('read_state.last_read_at')
                ->orWhereColumn('messages.created_at', '>', 'read_state.last_read_at'));
    }
}
