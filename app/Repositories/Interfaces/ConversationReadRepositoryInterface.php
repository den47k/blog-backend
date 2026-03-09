<?php

namespace App\Repositories\Interfaces;

use App\Models\Conversation;
use App\Models\User;
use Carbon\Carbon;

interface ConversationReadRepositoryInterface
{
    public function markAsRead(Conversation $conversation, User $user): void;

    public function getLastReadAt(User $user, Conversation $conversation): ?Carbon;
}
