<?php

namespace App\Listeners;

use App\Helpers\OnlineStatus;
use App\Services\UserStatusService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UpdateUserLastSeenTimestamp
{
    public function __construct(private UserStatusService $userStatusService) {}

    public function handleLogin(Login $event): void
    {
        $this->userStatusService->updateLastSeen($event->user->id);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            $this->userStatusService->updateLastSeen($event->user->id);
        }
    }

    /**
     * Handle the event.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'handleLogin']);
        $events->listen(Logout::class, [self::class, 'handleLogout']);
    }
}
