<?php

namespace App\Providers;

use App\Events\Conversation\ConversationCreatedEvent;
use App\Events\Conversation\ConversationDeletedEvent;
use App\Events\Message\MessageCreatedEvent;
use App\Events\Message\MessageDeletedEvent;
use App\Events\Message\MessageUpdatedEvent;
use App\Events\User\UserUpdatedEvent;
use App\Listeners\Cache\InvalidateOnConversationCreated;
use App\Listeners\Cache\InvalidateOnConversationDeleted;
use App\Listeners\Cache\InvalidateOnMessageCreated;
use App\Listeners\Cache\InvalidateOnMessageDeleted;
use App\Listeners\Cache\InvalidateOnMessageUpdated;
use App\Listeners\Cache\InvalidateOnUserUpdated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageCreatedEvent::class => [
            InvalidateOnMessageCreated::class,
        ],
        MessageUpdatedEvent::class => [
            InvalidateOnMessageUpdated::class,
        ],
        MessageDeletedEvent::class => [
            InvalidateOnMessageDeleted::class,
        ],
        ConversationCreatedEvent::class => [
            InvalidateOnConversationCreated::class,
        ],
        ConversationDeletedEvent::class => [
            InvalidateOnConversationDeleted::class,
        ],
        UserUpdatedEvent::class => [
            InvalidateOnUserUpdated::class,
        ],
    ];
}
