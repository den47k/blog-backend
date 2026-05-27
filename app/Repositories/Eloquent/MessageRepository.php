<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

class MessageRepository implements MessageRepositoryInterface
{
    public function getPaginated(Conversation $conversation, int $perPage = 30): CursorPaginator
    {
        return $conversation->messages()
            ->with('user:id,name,tag', 'attachment')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function create(Conversation $conversation, string $userId, ?string $content): Message
    {
        return $conversation->messages()->create([
            'user_id' => $userId,
            'content' => $content,
        ]);
    }

    public function update(Message $message, array $data): Message
    {
        $message->update($data);

        return $message;
    }

    public function delete(Message $message): void
    {
        $message->delete();
    }

    public function findLatestExcluding(Conversation $conversation, string $excludeMessageId): ?Message
    {
        return $conversation->messages()
            ->where('id', '!=', $excludeMessageId)
            ->latest()
            ->orderByDesc('id')
            ->first();
    }
}
