<?php

namespace App\Repositories\Redis;

use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationReadRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class ConversationReadRepository implements ConversationReadRepositoryInterface
{
    private const REDIS_LAST_READ_PREFIX = 'user';

    private const REDIS_CONVERSATION_PREFIX = 'conversation';

    public function markAsRead(Conversation $conversation, User $user): void
    {
        Redis::hset(
            $this->getRedisLastReadKey($user),
            $this->getRedisConversationField($conversation),
            now()->timestamp
        );
    }

    public function getLastReadAt(User $user, Conversation $conversation): ?Carbon
    {
        $timestamp = Redis::hget(
            $this->getRedisLastReadKey($user),
            $this->getRedisConversationField($conversation)
        );

        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    public function getAllLastReadTimestamps(User $user): array
    {
        $raw = Redis::hgetall($this->getRedisLastReadKey($user));
        $result = [];
        $prefixLength = strlen(self::REDIS_CONVERSATION_PREFIX.':');

        foreach ($raw as $field => $timestamp) {
            $conversationId = substr($field, $prefixLength);
            $result[$conversationId] = Carbon::createFromTimestamp($timestamp);
        }

        return $result;
    }

    private function getRedisLastReadKey(User $user): string
    {
        return self::REDIS_LAST_READ_PREFIX.":{$user->id}:last_read";
    }

    private function getRedisConversationField(Conversation $conversation): string
    {
        return self::REDIS_CONVERSATION_PREFIX.":{$conversation->id}";
    }
}
