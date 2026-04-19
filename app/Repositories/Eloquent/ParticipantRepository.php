<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use App\Repositories\Interfaces\ParticipantRepositoryInterface;
use Illuminate\Support\Collection;

class ParticipantRepository implements ParticipantRepositoryInterface
{
    public function add(Conversation $conversation, User $user, ?string $joinedAt = null, string $role = 'member'): Participant
    {
        return $conversation->participants()->updateOrCreate(
            ['user_id' => $user->id],
            ['joined_at' => $joinedAt, 'role' => $role]
        );
    }

    public function find(Conversation $conversation, string $userId): ?Participant
    {
        return $conversation->participants()
            ->where('user_id', $userId)
            ->first();
    }

    public function getAll(Conversation $conversation): Collection
    {
        return $conversation->participants()
            ->get(['user_id', 'joined_at']);
    }

    public function getOtherParticipants(Conversation $conversation, string $excludeUserId): Collection
    {
        return $conversation->participants()
            ->where('user_id', '!=', $excludeUserId)
            ->with('user')
            ->get()
            ->pluck('user');
    }

    public function markUnjoinedAsJoined(Conversation $conversation): void
    {
        $conversation->participants()
            ->whereNull('joined_at')
            ->update(['joined_at' => now()]);
    }
}
