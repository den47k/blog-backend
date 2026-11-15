<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'joined_at' => now(),
        ];
    }

    /**
     * A participant of a conversation that has no messages yet:
     * the conversation stays hidden from their list.
     */
    public function notJoined(): static
    {
        return $this->state(fn (array $attributes) => [
            'joined_at' => null,
        ]);
    }
}
