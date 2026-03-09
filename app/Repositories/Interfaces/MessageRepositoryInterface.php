<?php

namespace App\Repositories\Interfaces;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface
{
    public function getPaginated(Conversation $conversation, int $perPage = 30): LengthAwarePaginator;

    public function create(Conversation $conversation, string $userId, ?string $content): Message;

    public function update(Message $message, array $data): Message;

    public function delete(Message $message): void;

    public function findLatestExcluding(Conversation $conversation, string $excludeMessageId): ?Message;
}
