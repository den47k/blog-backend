<?php

namespace App\Repositories\Interfaces;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface ConversationRepositoryInterface
{
    public function getForUser(User $user, int $perPage = 20): CursorPaginator;

    public function findExistingPrivate(User $initiator, User $other): ?Conversation;

    public function create(array $data): Conversation;

    public function updateLastMessage(Conversation $conversation, ?string $messageId): void;

    public function delete(Conversation $conversation): void;
}
