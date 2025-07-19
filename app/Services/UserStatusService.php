<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class UserStatusService
{
    private const LAST_SEEN_KEY_PREFIX = 'user:last_seen_at:';

    public function updateLastSeen(string $userId): void
    {
        $key = self::LAST_SEEN_KEY_PREFIX . $userId;
        Redis::setex($key, 30 * 24 * 60 * 60, Carbon::now()->toIso8601String()); 
    }

    public function getLastSeenAt(string $userId): ?Carbon
    {
        $timestamp = Redis::get(self::LAST_SEEN_KEY_PREFIX . $userId);
        return $timestamp ? Carbon::parse($timestamp) : null;
    }

    public function getBulkLastSeen(array $userIds): array
    {
        if (empty($userIds)) return [];

        $keys = array_map(fn($id) => self::LAST_SEEN_KEY_PREFIX . $id, $userIds);
        $timestamps = Redis::mget($keys);

        $results = [];
        foreach ($userIds as $index => $userId) {
            $results[$userId] = $timestamps[$index] ? Carbon::parse($timestamps[$index]) : null;
        }

        return $results;
    }
}