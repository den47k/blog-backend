<?php

namespace App\Services;

use App\Events\ConversationDeletedEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\ConversationRedisRepository;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function __construct(
        private ConversationRedisRepository $redisRepository
    ) {}

    public function getConversationsForUser(User $user)
    {
        return $user->activeConversations()
            ->with([
                'participants.user:id,name,tag,avatar',
                'lastMessage:id,content,created_at'
            ])
            ->latest('updated_at')
            ->get();
    }

    public function getPrivateConversation(User $user, string $tag): Conversation
    {
        $targetUser = User::where('tag', $tag)->firstOrFail();
        $conversation = Conversation::findExistingConversation($user, $targetUser);

        if (!$conversation) {
            throw new ModelNotFoundException('Conversation not found');
        }

        return $conversation;
    }

    public function createPrivateConversation(User $initiator, string $recipientId, bool $should_join_now): Conversation
    {
        $other = User::findOrFail($recipientId);

        if ($initiator->id === $other->id) {
            throw new InvalidArgumentException('Cannot create conversation with yourself');
        }

        return DB::transaction(function () use ($initiator, $other, $should_join_now) {
            $existingConversation = Conversation::findExistingConversation($initiator, $other);

            if ($existingConversation) {
                if ($should_join_now) {
                    $participant = $existingConversation->participants()
                        ->where('user_id', $initiator->id)
                        ->first();

                    if ($participant && !$participant->joined_at) {
                        $participant->update(['joined_at' => now()]);

                        // $existingConversation->load(['participants.user', 'lastMessage']);  im not sure i need this broadcast at all
                        // broadcast(new ConversationCreated($existingConversation, $other));
                    }
                }
                return $existingConversation;
            }

            $conversation = Conversation::create([
                'conversation_type' => 'private',
                'is_public' => false,
            ]);

            $conversation->addParticipant($initiator, $should_join_now ? now() : null);
            $conversation->addParticipant($other, null);

            return $conversation;
        });
    }

    public function deleteConversation(Conversation $conversation, User $user): void
    {
        DB::transaction(function () use ($conversation, $user) {
            $userIds = $conversation->participants()
                ->whereNotNull('joined_at')
                ->where('user_id', '!=', $user->id)
                ->pluck('user_id')
                ->toArray();

            $conversationId = $conversation->id;
            $conversation->delete();

            $recipients = User::whereIn('id', $userIds)->get();
            broadcast(new ConversationDeletedEvent($conversationId, $recipients->all()));
        });
    }

    // public function createGroupConversation(): Conversation {}

    public function markConversationAsRead(Conversation $conversation, User $user): void
    {
        $this->redisRepository->markAsRead($conversation, $user);
    }

    public function hasUnreadMessages(Conversation $conversation, User $user): bool
    {
        $lastReadAt = $this->redisRepository->getLastReadAt($user, $conversation);
        $lastMessage = $conversation->lastMessage;

        if (!$lastMessage) return false;
        if ($lastReadAt) return $lastMessage->created_at->gt($lastReadAt);

        return true;
    }
}
