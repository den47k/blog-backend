<?php

namespace App\Listeners\Auth;

use App\Events\Auth\AuthAudited;
use Illuminate\Support\Facades\Log;

class LogAuditEvent
{
    public function handle(AuthAudited $event): void
    {
        Log::info('auth.audit.'.$event->action, [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'context' => $event->context,
        ]);
    }
}
