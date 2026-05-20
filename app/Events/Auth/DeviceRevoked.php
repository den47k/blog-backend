<?php

namespace App\Events\Auth;

class DeviceRevoked extends AuthAuditEvent
{
    public function eventName(): string
    {
        return 'device.revoked';
    }
}
