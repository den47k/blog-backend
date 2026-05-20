<?php

namespace App\Events\Auth;

class OAuthIdentityUnlinked extends AuthAuditEvent
{
    public function eventName(): string
    {
        return 'oauth.identity.unlinked';
    }
}
