<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ConversationService
{
    private const REDIS_LAST_READ_PREFIX = 'user';
    private const REDIS_CONVERSATION_PREFIX = 'conversation';

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

    public function createPrivateConversation(User $initiator, User $other, bool $should_join_now): Conversation
    {
        return DB::transaction(function () use ($initiator, $other, $should_join_now) {
            $existingConversation = Conversation::findExistingConversation($initiator, $other);

            if ($existingConversation) {
                // if ($should_join_now && $existingConversation->participants->isNotEmpty()) {
                //     $existingConversation->participants[0]->update(['joined_at' => now()]);
                // }

                if ($should_join_now) {
                    $participant = $existingConversation->participants()
                        ->where('user_id', $initiator->id)
                        ->first();

                    if ($participant) {
                        $participant->update(['joined_aat' => now()]);
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

    // public function createGroupConversation(): Conversation {}

    public function markConversationAsRead(Conversation $conversation, User $user): void
    {
        Redis::hset(
            $this->getRedisLastReadKey($user),
            $this->getRedisConversationField($conversation),
            now()->timestamp
        );
    }

    public function getLastReadAt(User $user, Conversation $conversation)
    {
        $timestamp = Redis::hget(
            $this->getRedisLastReadKey($user),
            $this->getRedisConversationField($conversation)
        );

        return $timestamp ? \Carbon\Carbon::createFromTimestamp($timestamp) : null;
    }

    private function getRedisLastReadKey(User $user): string
    {
        return self::REDIS_LAST_READ_PREFIX . ":{$user->id}:last_read";
    }

    private function getRedisConversationField(Conversation $conversation): string
    {
        return self::REDIS_CONVERSATION_PREFIX . ":{$conversation->id}";
    }
}
