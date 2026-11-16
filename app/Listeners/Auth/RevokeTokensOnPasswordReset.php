<?php

namespace App\Listeners\Auth;

use App\Repositories\UserRepository;
use App\Services\Auth\TokenService;
use Illuminate\Auth\Events\PasswordReset;

class RevokeTokensOnPasswordReset
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TokenService $tokenService,
    ) {}

    public function handle(PasswordReset $event): void
    {
        $this->userRepository->markPasswordChanged($event->user);
        $this->tokenService->revokeAll($event->user);
    }
}
