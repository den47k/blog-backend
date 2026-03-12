<?php

namespace App\Services;

use App\Events\ConversationDeletedEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationReadRepositoryInterface;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\ParticipantRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConversationService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ParticipantRepositoryInterface $participantRepository,
        private readonly ConversationReadRepositoryInterface $readRepository,
    ) {
    }

    public function getConversationsForUser(User $user)
    {
        return $this->conversationRepository->getForUser($user);
    }

    public function getPrivateConversation(User $user, string $tag): Conversation
    {
        $targetUser = $this->userRepository->findByTag($tag);
        $conversation = $this->conversationRepository->findExistingPrivate($user, $targetUser);

        if (!$conversation) {
            throw new ModelNotFoundException('Conversation not found');
        }

        return $conversation;
    }

    public function createPrivateConversation(User $initiator, string $recipientId, bool $should_join_now): Conversation
    {
        $other = $this->userRepository->findOrFail($recipientId);

        if ($initiator->id === $other->id) {
            throw new InvalidArgumentException('Cannot create conversation with yourself');
        }

        return DB::transaction(function () use ($initiator, $other, $should_join_now) {
            $existingConversation = $this->conversationRepository->findExistingPrivate($initiator, $other);

            if ($existingConversation) {
                if ($should_join_now) {
                    $participant = $this->participantRepository->find($existingConversation, $initiator->id);

                    if ($participant && !$participant->joined_at) {
                        $participant->update(['joined_at' => now()]);
                    }
                }
                return $existingConversation;
            }

            $conversation = $this->conversationRepository->create([
                'conversation_type' => 'private',
                'is_public' => false,
            ]);

            $this->participantRepository->add($conversation, $initiator, $should_join_now ? now() : null);
            $this->participantRepository->add($conversation, $other, null);

            return $conversation;
        });
    }

    public function deleteConversation(Conversation $conversation, User $user): void
    {
        DB::transaction(function () use ($conversation, $user) {
            $userIds = $this->participantRepository->getJoinedIdsExcept($conversation, $user->id);

            $conversationId = $conversation->id;
            $this->conversationRepository->delete($conversation);

            $recipients = $this->userRepository->findManyByIds($userIds);
            broadcast(new ConversationDeletedEvent($conversationId, $recipients->all()));
        });
    }

    public function markConversationAsRead(Conversation $conversation, User $user): void
    {
        $this->readRepository->markAsRead($conversation, $user);
    }

    public function getUnreadMap(User $user, iterable $conversations): array
    {
        $timestamps = $this->readRepository->getAllLastReadTimestamps($user);
        $map = [];

        foreach ($conversations as $conversation) {
            $lastMessage = $conversation->lastMessage;

            if (!$lastMessage) {
                $map[$conversation->id] = false;
                continue;
            }

            $lastReadAt = $timestamps[$conversation->id] ?? null;
            $map[$conversation->id] = $lastReadAt
                ? $lastMessage->created_at->gt($lastReadAt)
                : true;
        }

        return $map;
    }

    public function hasUnreadMessages(Conversation $conversation, User $user): bool
    {
        $lastMessage = $conversation->lastMessage;

        if (!$lastMessage) {
            return false;
        }

        $lastReadAt = $this->readRepository->getLastReadAt($user, $conversation);

        return $lastReadAt
            ? $lastMessage->created_at->gt($lastReadAt)
            : true;
    }
}
