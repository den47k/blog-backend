<?php

namespace App\Events\Auth;

class OAuthIdentityLinked extends AuthAuditEvent
{
    public function eventName(): string
    {
        return 'oauth.identity.linked';
    }
}
