<?php

namespace Tests\Feature\Realtime;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\TestCase;

class RealtimeTokenTest extends TestCase
{
    private function decode(string $jwt): array
    {
        return (array) JWT::decode($jwt, new Key(config('centrifugo.token_hmac_secret'), 'HS256'));
    }

    // ------------------------------------------------------ connection token

    public function test_connection_token_is_a_jwt_for_the_current_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)
            ->postJson('/api/realtime/token')
            ->assertOk()
            ->assertJsonStructure(['token', 'url'])
            ->assertJsonPath('url', 'ws://centrifugo.test/connection/websocket');

        $claims = $this->decode($response->json('token'));

        $this->assertSame($user->id, $claims['sub']);
        $this->assertArrayNotHasKey('channel', $claims);
        $this->assertEqualsWithDelta(time() + 3600, $claims['exp'], 5);
    }

    public function test_connection_token_requires_authentication(): void
    {
        $this->postJson('/api/realtime/token')->assertUnauthorized();
    }

    // ---------------------------------------------------- subscription token

    public function test_user_can_subscribe_to_their_own_user_channel(): void
    {
        $user = User::factory()->create();
        $channel = "user:{$user->id}";

        $response = $this->actingAsUser($user)
            ->postJson('/api/realtime/subscribe', ['channel' => $channel])
            ->assertOk()
            ->assertJsonStructure(['token', 'channel'])
            ->assertJsonPath('channel', $channel);

        $claims = $this->decode($response->json('token'));

        $this->assertSame($user->id, $claims['sub']);
        $this->assertSame($channel, $claims['channel']);
        $this->assertEqualsWithDelta(time() + 900, $claims['exp'], 5);
    }

    public function test_user_cannot_subscribe_to_another_users_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/realtime/subscribe', ['channel' => "user:{$other->id}"])
            ->assertForbidden();
    }

    public function test_the_removed_conversation_namespace_is_rejected_even_for_participants(): void
    {
        $user = User::factory()->create();
        $conversation = $this->privateConversation($user, User::factory()->create());

        $this->actingAsUser($user)
            ->postJson('/api/realtime/subscribe', ['channel' => "conv:{$conversation->id}"])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Forbidden.']);
    }

    public function test_unknown_namespaces_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        foreach (["other:{$user->id}", "user.{$user->id}", "private-user.{$user->id}", 'public'] as $channel) {
            $this->postJson('/api/realtime/subscribe', ['channel' => $channel])
                ->assertForbidden();
        }
    }

    public function test_subscribe_requires_a_channel(): void
    {
        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/realtime/subscribe', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channel');
    }

    public function test_subscribe_requires_authentication(): void
    {
        $this->postJson('/api/realtime/subscribe', ['channel' => 'user:1'])->assertUnauthorized();
    }
}
