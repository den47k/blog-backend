<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    public const TTL_USER_PROFILE = 900;
    public const TTL_USER_CONVERSATIONS = 60;
    public const TTL_CONV_MESSAGES_PAGE_1 = 300;

    private const LOCK_TIMEOUT = 5;
    private const LOCK_WAIT = 3;

    public static function userProfile(string $userId): string
    {
        return "user:{$userId}:profile";
    }

    public static function userConversations(string $userId): string
    {
        return "user:{$userId}:conversations";
    }

    public static function convMessagesPage1(string $conversationId): string
    {
        return "conv:{$conversationId}:messages:page:1";
    }

    public static function rememberWithLock(string $key, int $ttl, Closure $callback): mixed
    {
        if (($hit = Cache::get($key)) !== null) {
            return $hit;
        }

        $lock = Cache::lock("lock:{$key}", self::LOCK_TIMEOUT);

        try {
            $lock->block(self::LOCK_WAIT);

            if (($hit = Cache::get($key)) !== null) {
                return $hit;
            }

            $value = $callback();
            Cache::put($key, $value, self::jitter($ttl));

            return $value;
        } finally {
            optional($lock)->release();
        }
    }

    private static function jitter(int $ttl): int
    {
        $delta = (int) round($ttl * 0.1);

        return $ttl + random_int(-$delta, $delta);
    }
}
