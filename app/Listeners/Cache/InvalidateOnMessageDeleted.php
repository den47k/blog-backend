<?php

namespace App\Listeners\Cache;

use App\Events\MessageDeletedEvent;
use App\Support\Cache\CacheHelper;
use Illuminate\Support\Facades\Cache;

class InvalidateOnMessageDeleted
{
    public function handle(MessageDeletedEvent $event): void
    {
        Cache::forget(CacheHelper::convMessagesPage1($event->conversation->id));
        Cache::forget(CacheHelper::userConversations($event->recipient->id));
        Cache::forget(CacheHelper::userConversations($event->authorId));
    }
}
