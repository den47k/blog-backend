<?php

namespace App\Listeners\Cache;

use App\Events\MessageCreatedEvent;
use App\Support\Cache\CacheHelper;
use Illuminate\Support\Facades\Cache;

class InvalidateOnMessageCreated
{
    public function handle(MessageCreatedEvent $event): void
    {
        $message = $event->message;

        Cache::forget(CacheHelper::convMessagesPage1($message->conversation_id));
        Cache::forget(CacheHelper::userConversations($message->user_id));

        foreach ($event->recipients as $recipient) {
            Cache::forget(CacheHelper::userConversations($recipient->id));
        }
    }
}
