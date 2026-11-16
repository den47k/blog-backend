<?php

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuthAudited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly User $user,
        public readonly array $context = [],
    ) {}
}
