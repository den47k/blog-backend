<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'user.{userId}',
    fn(User $user, $userId) => (int) $user->id === (int) $userId,
);

Broadcast::channel(
    'conversation.{conversationId}',
    fn($user, $conversationId) => Conversation::where('id', $conversationId)
        ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
        ->exists(),
);
