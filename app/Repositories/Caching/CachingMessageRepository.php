<?php

namespace App\Repositories\Caching;

use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use App\Support\Cache\CacheHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CachingMessageRepository implements MessageRepositoryInterface
{
    private const DEFAULT_PER_PAGE = 30;

    public function __construct(private readonly MessageRepositoryInterface $inner)
    {
    }

    public function getPaginated(Conversation $conversation, int $perPage = 30): LengthAwarePaginator
    {
        if ($perPage !== self::DEFAULT_PER_PAGE || (int) request()->input('page', 1) !== 1) {
            return $this->inner->getPaginated($conversation, $perPage);
        }

        return CacheHelper::rememberWithLock(
            CacheHelper::convMessagesPage1($conversation->id),
            CacheHelper::TTL_CONV_MESSAGES_PAGE_1,
            fn () => $this->inner->getPaginated($conversation, $perPage),
        );
    }

    public function create(Conversation $conversation, string $userId, ?string $content): Message
    {
        return $this->inner->create($conversation, $userId, $content);
    }

    public function update(Message $message, array $data): Message
    {
        return $this->inner->update($message, $data);
    }

    public function delete(Message $message): void
    {
        $this->inner->delete($message);
    }

    public function findLatestExcluding(Conversation $conversation, string $excludeMessageId): ?Message
    {
        return $this->inner->findLatestExcluding($conversation, $excludeMessageId);
    }
}
