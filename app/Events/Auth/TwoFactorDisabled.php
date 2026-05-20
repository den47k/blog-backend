<?php

namespace App\Events\Auth;

class TwoFactorDisabled extends AuthAuditEvent
{
    public function eventName(): string
    {
        return 'two_factor.disabled';
    }
}
