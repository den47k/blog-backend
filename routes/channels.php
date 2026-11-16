<?php

use App\Models\User;
use App\Repositories\Eloquent\ConversationRepository;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

Broadcast::channel(
    'user.{userId}',
    fn (User $user, string $userId) => $user->id === $userId,
);

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $conversation = Str::isUuid($conversationId) ? app(ConversationRepository::class)->find($conversationId) : null;

    return $conversation !== null && $user->can('view', $conversation);
});
