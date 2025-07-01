<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function getConversationsForUser(User $user)
    {
        return $user->activeConversations()
            ->with([
                'participants.user:id,name,tag',
                'lastMessage:id,content,created_at,conversation_id'
            ])
            ->latest('updated_at')
            ->get()
            ->map(function ($conversation) use ($user) {
                $conversation->other_participant = $conversation->participants
                    ->where('user_id', '!=', $user->id)
                    ->first();
                return $conversation;
            });
    }

    public function createPrivateConversation(User $initiator, User $other, bool $should_join_now): Conversation
    {
        return DB::transaction(function () use ($initiator, $other, $should_join_now) {
            $existingConversation = Conversation::findExistingConversation($initiator, $other);

            if ($existingConversation) {
                if ($should_join_now && $existingConversation->participants->isNotEmpty()) {
                    $existingConversation->participants[0]->update(['joined_at' => now()]);
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
}
