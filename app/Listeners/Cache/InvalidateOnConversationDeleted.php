<?php

namespace App\Listeners\Cache;

use App\Events\ConversationDeletedEvent;
use App\Support\Cache\CacheHelper;
use Illuminate\Support\Facades\Cache;

class InvalidateOnConversationDeleted
{
    public function handle(ConversationDeletedEvent $event): void
    {
        Cache::forget(CacheHelper::convMessagesPage1($event->conversationId));

        foreach ($event->affectedUserIds as $userId) {
            Cache::forget(CacheHelper::userConversations($userId));
        }
    }
}
