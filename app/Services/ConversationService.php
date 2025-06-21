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
                'lastMessage:id,body,created_at,conversation_id'
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

    public function createPrivateConversation(User $initiator, User $other): Conversation
    {
        return DB::transaction(function () use ($initiator, $other) {
            if ($existingConversation = Conversation::findExistingConversation($initiator, $other)) {
                $this->ensureParticipantJoined($existingConversation, $initiator);
                $this->ensureParticipantJoined($existingConversation, $other);

                return $existingConversation;
            }

            $conversation = Conversation::create([
                'conversation_type' => 'private',
                'is_public' => false,
            ]);

            $conversation->addParticipant($initiator, now());
            $conversation->addParticipant($other, null);

            return $conversation;
        });
    }

    // public function createGroupConversation(): Conversation {}

    protected function ensureParticipantJoined(Conversation $conversation, User $user): void
    {
        $participant = $conversation->participants()->where('user_id', $user->id)->first();

        if ($participant && is_null($participant->joined_at)) {
            $participant->update(['joined_at' => now()]);
        }
    }
}
