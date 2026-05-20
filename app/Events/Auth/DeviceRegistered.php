<?php

namespace App\Events\Auth;

class DeviceRegistered extends AuthAuditEvent
{
    public function eventName(): string
    {
        return 'device.registered';
    }
}
