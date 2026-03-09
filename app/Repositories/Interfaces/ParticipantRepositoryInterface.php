<?php

namespace App\Repositories\Interfaces;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Collection;

interface ParticipantRepositoryInterface
{
    public function add(Conversation $conversation, User $user, ?string $joinedAt = null, string $role = 'member'): Participant;

    public function find(Conversation $conversation, string $userId): ?Participant;

    public function getJoinedIdsExcept(Conversation $conversation, string $excludeUserId): array;

    public function getOtherParticipants(Conversation $conversation, string $excludeUserId): Collection;

    public function markUnjoinedAsJoined(Conversation $conversation): void;
}
