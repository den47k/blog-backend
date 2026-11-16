<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Eloquent\ParticipantRepository;

class ConversationPolicy
{
    public function __construct(
        private readonly ParticipantRepository $participantRepository,
    ) {}

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function markAsRead(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    private function isParticipant(User $user, Conversation $conversation): bool
    {
        return $this->participantRepository->exists($conversation, $user->id);
    }
}
