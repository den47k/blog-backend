<?php

namespace App\Events\Auth;

class TwoFactorEnabled extends AuthAuditEvent
{
    public function eventName(): string
    {
        return 'two_factor.enabled';
    }
}
