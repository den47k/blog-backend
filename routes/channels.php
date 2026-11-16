<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

Broadcast::channel(
    'user.{userId}',
    fn (User $user, string $userId) => $user->id === $userId,
);

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $conversation = Str::isUuid($conversationId) ? Conversation::find($conversationId) : null;

    return $conversation !== null && $user->can('view', $conversation);
});
