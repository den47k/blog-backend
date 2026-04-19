<?php

namespace App\Listeners\Cache;

use App\Events\ConversationCreatedEvent;
use App\Support\Cache\CacheHelper;
use Illuminate\Support\Facades\Cache;

class InvalidateOnConversationCreated
{
    public function handle(ConversationCreatedEvent $event): void
    {
        Cache::forget(CacheHelper::userConversations($event->recipient->id));
    }
}
