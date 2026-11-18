<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ParticipantRepository
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

    public function exists(Conversation $conversation, string $userId): bool
    {
        return $conversation->participants()
            ->where('user_id', $userId)
            ->exists();
    }

    public function markJoined(Participant $participant): void
    {
        $participant->update(['joined_at' => now()]);
    }

    /**
     * Written as a formatted string: the model's date format would cut the
     * microseconds the read-state comparison relies on.
     */
    public function markRead(Conversation $conversation, string $userId, ?CarbonInterface $at = null): void
    {
        $conversation->participants()
            ->where('user_id', $userId)
            ->update(['last_read_at' => ($at ?? now())->format('Y-m-d H:i:s.u')]);
    }

    public function getAll(Conversation $conversation): Collection
    {
        return $conversation->participants()
            ->get(['user_id', 'joined_at']);
    }

    public function getUsers(Conversation $conversation): Collection
    {
        return $conversation->participants()
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
