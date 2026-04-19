<?php

namespace App\Listeners\Cache;

use App\Events\UserUpdatedEvent;
use App\Support\Cache\CacheHelper;
use Illuminate\Support\Facades\Cache;

class InvalidateOnUserUpdated
{
    public function handle(UserUpdatedEvent $event): void
    {
        Cache::forget(CacheHelper::userProfile($event->user->id));
    }
}
