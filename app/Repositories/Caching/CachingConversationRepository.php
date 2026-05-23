<?php

namespace App\Repositories\Caching;

use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Support\Cache\CacheHelper;
use Illuminate\Contracts\Pagination\CursorPaginator;

class CachingConversationRepository implements ConversationRepositoryInterface
{
    private const DEFAULT_PER_PAGE = 20;

    public function __construct(private readonly ConversationRepositoryInterface $inner) {}

    public function getForUser(User $user, int $perPage = 20): CursorPaginator
    {
        if ($perPage !== self::DEFAULT_PER_PAGE || request()->filled('cursor')) {
            return $this->inner->getForUser($user, $perPage);
        }

        return CacheHelper::rememberWithLock(
            CacheHelper::userConversations($user->id),
            CacheHelper::TTL_USER_CONVERSATIONS,
            fn () => $this->inner->getForUser($user, $perPage),
        );
    }

    public function findExistingPrivate(User $initiator, User $other): ?Conversation
    {
        return $this->inner->findExistingPrivate($initiator, $other);
    }

    public function create(array $data): Conversation
    {
        return $this->inner->create($data);
    }

    public function updateLastMessage(Conversation $conversation, ?string $messageId): void
    {
        $this->inner->updateLastMessage($conversation, $messageId);
    }

    public function delete(Conversation $conversation): void
    {
        $this->inner->delete($conversation);
    }
}
