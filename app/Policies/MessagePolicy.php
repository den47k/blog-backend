<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->participants()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Message $message): bool
    {
        return $user->id === $message->user_id;
    }

    public function delete(User $user, Message $message): bool
    {
        Log::info($user);
        return $user->id === $message->user_id;
    }
}
