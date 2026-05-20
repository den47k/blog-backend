<?php

namespace App\Listeners\Auth;

use App\Events\Auth\AuthAuditEvent;
use Illuminate\Support\Facades\Log;

class LogAuditEvent
{
    public function handle(AuthAuditEvent $event): void
    {
        Log::info('auth.audit.'.$event->eventName(), [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'context' => $event->context,
        ]);
    }
}
