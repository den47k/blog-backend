<?php

namespace App\Services\Auth;

use App\Enums\AuthStatus;
use App\Models\Device;
use App\Models\User;

/**
 * Outcome of an authentication step. The token is a full access token only
 * for Authenticated; every other status carries a short-lived, single-purpose one.
 */
final readonly class AuthResult
{
    public function __construct(
        public AuthStatus $status,
        public string $token,
        public ?User $user = null,
        public ?Device $device = null,
        public ?int $expiresIn = null,
    ) {}
}
