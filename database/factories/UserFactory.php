<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Default plaintext password of every factory user.
     */
    public const PASSWORD = 'password';

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Must match ^[a-zA-Z][a-zA-Z0-9_]{4,31}$ (RegisterProfileRequest).
            'tag' => 'user_'.Str::lower(Str::random(12)),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Registered, e-mail not confirmed yet: no name, no tag.
     */
    public function pendingEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'tag' => null,
            'email_verified_at' => null,
            'status' => UserStatus::PendingEmail,
        ]);
    }

    /**
     * E-mail confirmed, profile (name + tag) not filled in yet.
     */
    public function pendingProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'tag' => null,
            'email_verified_at' => now(),
            'status' => UserStatus::PendingProfile,
        ]);
    }
}
