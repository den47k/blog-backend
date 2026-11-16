<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_password_reset_is_limited_to_5_attempts_a_minute_per_email(): void
    {
        $payload = [
            'email' => 'someone@example.com',
            'token' => 'wrong',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/reset-password', $payload)->assertTooManyRequests();
    }

    public function test_oauth_endpoints_are_limited_to_20_requests_a_minute(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/auth/oauth/github/callback')->assertBadRequest();
        }

        $this->getJson('/api/auth/oauth/github/callback')->assertTooManyRequests();
    }

    public function test_password_confirmations_are_limited_to_5_a_minute_per_user(): void
    {
        $this->actingAsUser(User::factory()->create());

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/2fa/disable', ['password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/2fa/disable', ['password' => 'wrong'])->assertTooManyRequests();
        // The limit is shared by every endpoint that takes the password.
        $this->postJson('/api/auth/2fa/recovery-codes', ['password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_sending_messages_is_limited_to_60_a_minute_per_user(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($author, $other);
        $url = "/api/conversations/{$conversation->id}/messages";

        $this->actingAsUser($author);
        for ($i = 0; $i < 60; $i++) {
            $this->postJson($url, ['content' => 'hi'])->assertCreated();
        }
        $this->postJson($url, ['content' => 'hi'])->assertTooManyRequests();

        $this->actingAsUser($other)->postJson($url, ['content' => 'hi'])->assertCreated();
    }

    public function test_user_search_is_limited_to_30_requests_a_minute_per_user(): void
    {
        $this->actingAsUser(User::factory()->create());

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/users/search?query=al')->assertOk();
        }

        $this->getJson('/api/users/search?query=al')->assertTooManyRequests();
    }
}
