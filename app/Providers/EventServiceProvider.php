<?php

namespace App\Providers;

use App\Events\ConversationCreatedEvent;
use App\Events\ConversationDeletedEvent;
use App\Events\MessageCreatedEvent;
use App\Events\MessageDeletedEvent;
use App\Events\MessageUpdatedEvent;
use App\Events\UserUpdatedEvent;
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
