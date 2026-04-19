<?php

namespace App\Repositories\Caching;

use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Support\Cache\CacheHelper;
use Illuminate\Support\Collection;

class CachingConversationRepository implements ConversationRepositoryInterface
{
    public function __construct(private readonly ConversationRepositoryInterface $inner)
    {
    }

    public function getForUser(User $user): Collection
    {
        return CacheHelper::rememberWithLock(
            CacheHelper::userConversations($user->id),
            CacheHelper::TTL_USER_CONVERSATIONS,
            fn () => $this->inner->getForUser($user),
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
