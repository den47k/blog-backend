<?php

namespace Tests\Feature\Auth;

use App\Enums\TokenAbility;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Which token may call which route group. Intermediate tokens (registration,
 * 2FA challenge, OAuth link) must never work as a session.
 */
class TokenAbilityTest extends TestCase
{
    use InteractsWithAuth;

    public static function pendingAbilities(): array
    {
        return [
            'registration token' => [TokenAbility::RegisterPending],
            '2fa challenge token' => [TokenAbility::TwoFactorPending],
            'oauth link token' => [TokenAbility::OAuthLinkPending],
        ];
    }

    #[DataProvider('pendingAbilities')]
    public function test_a_pending_token_cannot_be_used_as_a_session(TokenAbility $ability): void
    {
        $user = User::factory()->create();
        $this->withBearer($this->tokenFor($user, [$ability]));

        $this->getJson('/api/user')->assertForbidden();
        $this->getJson('/api/conversations')->assertForbidden();
        $this->getJson('/api/users/search?query=someone')->assertForbidden();
        $this->getJson('/api/auth/devices')->assertForbidden();
        $this->postJson('/api/realtime/token')->assertForbidden();
        $this->postJson('/api/auth/2fa/enable')->assertForbidden();
        $this->postJson('/api/auth/logout')->assertForbidden();
        $this->postJson('/api/auth/logout/all')->assertForbidden();
    }

    public function test_a_registration_token_cannot_pass_the_two_factor_or_link_steps(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $this->withBearer($this->tokenFor($user, [TokenAbility::RegisterPending]));

        $this->postJson('/api/auth/2fa/verify', ['code' => '123456', 'device_name' => 'x'])->assertForbidden();
        $this->postJson('/api/auth/oauth/github/link/confirm', ['password' => 'x', 'device_name' => 'x'])
            ->assertForbidden();
    }

    public function test_a_two_factor_challenge_token_cannot_pass_the_registration_or_link_steps(): void
    {
        $user = User::factory()->pendingProfile()->create();
        $this->withBearer($this->tokenFor($user, [TokenAbility::TwoFactorPending]));

        $this->postJson('/api/auth/register/profile', ['name' => 'Name', 'tag' => 'valid_tag', 'device_name' => 'x'])
            ->assertForbidden();
        $this->postJson('/api/auth/register/verify-email', ['otp' => '123456'])->assertForbidden();
        $this->getJson('/api/auth/register/suggest-tag')->assertForbidden();
        $this->postJson('/api/auth/oauth/github/link/confirm', ['password' => 'x', 'device_name' => 'x'])
            ->assertForbidden();
    }

    public function test_an_oauth_link_token_cannot_pass_the_registration_or_two_factor_steps(): void
    {
        $user = User::factory()->pendingProfile()->create();
        $this->withBearer($this->tokenFor($user, [TokenAbility::OAuthLinkPending]));

        $this->postJson('/api/auth/register/profile', ['name' => 'Name', 'tag' => 'valid_tag', 'device_name' => 'x'])
            ->assertForbidden();
        $this->postJson('/api/auth/2fa/verify', ['code' => '123456', 'device_name' => 'x'])->assertForbidden();
    }

    /**
     * A session token holds the wildcard ability, so it passes the ability
     * middleware of the pending-only routes; those are protected by the
     * user's state instead.
     */
    public function test_a_session_token_is_stopped_on_registration_routes_by_the_user_status(): void
    {
        $user = User::factory()->create();
        $this->withBearer($this->tokenFor($user));

        $this->postJson('/api/auth/register/verify-email', ['otp' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->postJson('/api/auth/register/profile', ['name' => 'Name', 'tag' => 'valid_tag', 'device_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_an_expired_pending_token_is_rejected(): void
    {
        $user = User::factory()->pendingEmail()->create();
        $token = $this->tokenFor($user, [TokenAbility::RegisterPending], now()->subMinute());

        $this->withBearer($token)
            ->postJson('/api/auth/register/verify-email', ['otp' => '123456'])
            ->assertUnauthorized();
    }

    public function test_a_session_of_a_user_who_is_not_active_cannot_reach_the_application(): void
    {
        $user = User::factory()->pendingProfile()->create();
        $this->withBearer($this->tokenFor($user));

        $this->getJson('/api/conversations')->assertForbidden();
        $this->getJson('/api/users/search?query=someone')->assertForbidden();
        $this->postJson('/api/realtime/token')->assertForbidden();
    }

    public function test_requests_without_a_token_are_unauthenticated(): void
    {
        $this->getJson('/api/user')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->getJson('/api/conversations')->assertUnauthorized();
        $this->postJson('/api/auth/register/verify-email')->assertUnauthorized();
        $this->postJson('/api/auth/2fa/verify')->assertUnauthorized();
    }

    public function test_a_garbage_token_is_unauthenticated(): void
    {
        $this->withBearer('not-a-real-token')->getJson('/api/user')->assertUnauthorized();
    }
}
