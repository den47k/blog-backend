<?php

namespace App\Listeners\Auth;

use App\Services\Auth\DeviceService;
use Laravel\Sanctum\Events\TokenAuthenticated;

class TouchDeviceOnAuthentication
{
    public function __construct(
        private readonly DeviceService $deviceService,
    ) {}

    public function handle(TokenAuthenticated $event): void
    {
        $this->deviceService->touch(
            $event->token->id,
            request()?->ip(),
        );
    }
}
