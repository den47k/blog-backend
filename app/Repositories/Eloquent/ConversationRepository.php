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
                'lastMessage:id,content,created_at,updated_at',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findExistingPrivate(User $initiator, User $other): ?Conversation
    {
        return $this->selectUnreadFor(Conversation::query(), $initiator->id)
            ->where('conversation_type', 'private')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $initiator->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $other->id))
            ->has('participants', '=', 2)
            ->with('lastMessage:id,content,created_at,updated_at')
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

    /**
     * Adds the `has_unread` column for the given user.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function selectUnreadFor($query, string $userId)
    {
        return $query
            ->select('conversations.*')
            ->selectRaw('exists ('.$this->unreadFor($userId)->toSql().') as has_unread', [$userId]);
    }

    /**
     * Correlated to the outer `conversations` row: its last message is newer
     * than the user's read mark, or the user has never read the conversation.
     */
    private function unreadFor(string $userId): QueryBuilder
    {
        return DB::table('messages')
            ->join('participants as read_state', 'read_state.conversation_id', '=', 'messages.conversation_id')
            ->selectRaw('1')
            ->whereColumn('messages.id', 'conversations.last_message_id')
            ->where('read_state.user_id', $userId)
            ->where(fn (QueryBuilder $q) => $q
                ->whereNull('read_state.last_read_at')
                ->orWhereColumn('messages.created_at', '>', 'read_state.last_read_at'));
    }
}
