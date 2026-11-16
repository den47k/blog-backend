<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function update(User $user, Message $message): bool
    {
        return $this->isAuthorInConversation($user, $message);
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->isAuthorInConversation($user, $message);
    }

    private function isAuthorInConversation(User $user, Message $message): bool
    {
        return $user->id === $message->user_id
            && $user->can('view', $message->conversation);
    }
}
