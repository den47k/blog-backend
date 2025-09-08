<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

class ConversationRedisRepository
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

    public function getLastReadAt(User $user, Conversation $conversation)
    {
        $timestamp = Redis::hget(
            $this->getRedisLastReadKey($user),
            $this->getRedisConversationField($conversation)
        );

        return $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : null;
    }

    private function getRedisLastReadKey(User $user): string
    {
        return self::REDIS_LAST_READ_PREFIX . ":{$user->id}:last_read";
    }

    private function getRedisConversationField(Conversation $conversation): string
    {
        return self::REDIS_CONVERSATION_PREFIX . ":{$conversation->id}";
    }
}
