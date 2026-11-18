<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'user.{userId}',
    fn (User $user, string $userId) => $user->id === $userId,
);
