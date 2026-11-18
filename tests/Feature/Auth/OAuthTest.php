<?php

namespace Tests\Feature\Auth;

use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\OAuthIdentity;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use InteractsWithAuth;

    private const STATE = 'test-state-nonce';

    private const PENDING_KEYS = ['status', 'registration_token', 'token_type', 'expires_in'];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * Makes the provider return this account when the callback exchanges the code.
     */
    private function fakeProviderUser(string $provider, string $id, ?string $email, array $raw = []): void
    {
        $socialUser = (new SocialiteUser)
            ->setRaw($raw)
            ->map(['id' => $id, 'name' => 'Provider Name', 'email' => $email]);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    private function storeState(string $provider): void
    {
        Cache::put('oauth:state:'.self::STATE, ['provider' => $provider, 'issued_at' => time()], 600);
    }

    private function oauthCallback(string $provider)
    {
        return $this->getJson("/api/auth/oauth/{$provider}/callback?code=abc&state=".self::STATE);
    }

    private function identityFor(User $user, string $provider = 'github', string $uid = 'gh-1'): OAuthIdentity
    {
        return OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $uid,
            'provider_email' => $user->email,
            'provider_email_verified' => true,
            'linked_at' => now(),
        ]);
    }

    // -------------------------------------------------------------- redirect

    public function test_redirect_returns_the_provider_url_and_remembers_the_state(): void
    {
        config(['services.github' => [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect' => 'http://localhost:5173/oauth/callback/github',
        ]]);

        $url = $this->getJson('/api/auth/oauth/github/redirect')
            ->assertOk()
            ->assertJsonStructure(['url'])
            ->json('url');

        $this->assertStringStartsWith('https://github.com/login/oauth/authorize', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('github', Cache::get('oauth:state:'.$query['state'])['provider']);
    }

    public function test_unsupported_providers_do_not_exist(): void
    {
        $this->getJson('/api/auth/oauth/facebook/redirect')->assertNotFound();
        $this->getJson('/api/auth/oauth/facebook/callback?code=abc&state=x')->assertNotFound();
    }

    // ----------------------------------------------------------------- state

    public function test_callback_requires_a_state(): void
    {
        $this->getJson('/api/auth/oauth/github/callback?code=abc')->assertBadRequest();
    }

    public function test_callback_rejects_an_unknown_state(): void
    {
        $this->oauthCallback('github')->assertBadRequest();
    }

    public function test_callback_rejects_a_state_issued_for_another_provider(): void
    {
        $this->storeState('google');

        $this->oauthCallback('github')->assertBadRequest();
    }

    public function test_a_state_can_be_used_only_once(): void
    {
        $user = User::factory()->create();
        $this->identityFor($user);
        $this->fakeProviderUser('github', 'gh-1', $user->email);
        $this->storeState('github');

        $this->oauthCallback('github')->assertOk();
        $this->oauthCallback('github')->assertBadRequest();
    }

    // -------------------------------------------------------------- callback

    public function test_callback_logs_in_the_owner_of_a_known_identity(): void
    {
        $user = User::factory()->create();
        $this->identityFor($user);
        $this->fakeProviderUser('github', 'gh-1', $user->email);
        $this->storeState('github');

        $response = $this->oauthCallback('github')
            ->assertOk()
            ->assertJsonStructure(self::LOGIN_SUCCESS_KEYS)
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.id', $user->id);

        // The device is named after the provider unless the client names it.
        $this->assertDatabaseHas('devices', ['id' => $response->json('device_id'), 'device_name' => 'github']);
    }

    public function test_callback_asks_for_the_second_factor_when_enabled(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $this->identityFor($user);
        $this->fakeProviderUser('github', 'gh-1', $user->email);
        $this->storeState('github');

        $this->oauthCallback('github')
            ->assertOk()
            ->assertJsonStructure(self::TWO_FACTOR_REQUIRED_KEYS)
            ->assertJsonPath('status', 'two_factor_required');

        $this->assertSame(0, $user->devices()->count());
    }

    public function test_callback_resumes_the_profile_step_for_a_user_who_never_finished_it(): void
    {
        $user = User::factory()->pendingProfile()->create();
        $this->identityFor($user);
        $this->fakeProviderUser('github', 'gh-1', $user->email);
        $this->storeState('github');

        $this->oauthCallback('github')
            ->assertOk()
            ->assertJsonStructure(self::PENDING_KEYS)
            ->assertJsonMissingPath('token')
            ->assertJsonPath('status', 'pending_profile');

        $this->assertSame([TokenAbility::RegisterPending->value], $user->tokens()->sole()->abilities);
        $this->assertSame(0, $user->devices()->count());
    }

    public function test_callback_resumes_the_email_step_for_a_user_who_never_verified(): void
    {
        $user = User::factory()->pendingEmail()->create();
        $this->identityFor($user);
        $this->fakeProviderUser('github', 'gh-1', $user->email);
        $this->storeState('github');

        $this->oauthCallback('github')
            ->assertOk()
            ->assertJsonStructure(self::PENDING_KEYS)
            ->assertJsonMissingPath('token')
            ->assertJsonPath('status', 'pending_email');

        $this->assertSame([TokenAbility::RegisterPending->value], $user->tokens()->sole()->abilities);
        Notification::assertSentTo($user, EmailOtpNotification::class);
    }

    public function test_callback_does_not_log_in_an_auto_linked_user_with_an_unfinished_profile(): void
    {
        $user = User::factory()->pendingProfile()->create();
        $this->fakeProviderUser('google', 'g-1', $user->email, ['email_verified' => true]);
        $this->storeState('google');

        $this->oauthCallback('google')
            ->assertOk()
            ->assertJsonStructure(self::PENDING_KEYS)
            ->assertJsonMissingPath('token')
            ->assertJsonPath('status', 'pending_profile');

        $this->assertDatabaseHas('oauth_identities', ['user_id' => $user->id, 'provider' => 'google']);
    }

    public function test_callback_with_a_verified_new_email_starts_at_the_profile_step(): void
    {
        $this->fakeProviderUser('github', 'gh-new', 'fresh@example.com');
        $this->storeState('github');

        $this->oauthCallback('github')
            ->assertOk()
            ->assertJsonStructure(self::PENDING_KEYS)
            ->assertJsonPath('status', 'pending_profile')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800);

        $user = User::where('email', 'fresh@example.com')->firstOrFail();

        $this->assertSame(UserStatus::PendingProfile, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->tag);
        $this->assertDatabaseHas('oauth_identities', [
            'user_id' => $user->id,
            'provider' => 'github',
            'provider_user_id' => 'gh-new',
        ]);
        $this->assertSame([TokenAbility::RegisterPending->value], $user->tokens()->sole()->abilities);

        Notification::assertNothingSent();
    }

    public function test_callback_with_an_unverified_new_email_starts_at_the_email_step(): void
    {
        $this->fakeProviderUser('google', 'g-new', 'fresh@example.com', ['email_verified' => false]);
        $this->storeState('google');

        $this->oauthCallback('google')
            ->assertOk()
            ->assertJsonStructure(self::PENDING_KEYS)
            ->assertJsonPath('status', 'pending_email');

        $user = User::where('email', 'fresh@example.com')->firstOrFail();

        $this->assertSame(UserStatus::PendingEmail, $user->status);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, EmailOtpNotification::class);
    }

    public function test_callback_links_automatically_when_both_sides_have_verified_the_email(): void
    {
        $user = User::factory()->create();
        $this->fakeProviderUser('google', 'g-1', $user->email, ['email_verified' => true]);
        $this->storeState('google');

        $this->oauthCallback('google')
            ->assertOk()
            ->assertJsonStructure(self::LOGIN_SUCCESS_KEYS)
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('oauth_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g-1',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_callback_requires_a_password_confirmation_when_the_email_is_not_verified_on_both_sides(): void
    {
        $user = User::factory()->create();
        $this->fakeProviderUser('google', 'g-1', $user->email, ['email_verified' => false]);
        $this->storeState('google');

        $this->oauthCallback('google')
            ->assertOk()
            ->assertJsonStructure(['status', 'link_token', 'token_type', 'expires_in'])
            ->assertJsonPath('status', 'link_required')
            ->assertJsonPath('expires_in', 600);

        $this->assertDatabaseCount('oauth_identities', 0);
        $this->assertSame([TokenAbility::OAuthLinkPending->value], $user->tokens()->sole()->abilities);
    }

    // ---------------------------------------------------------- confirm link

    /**
     * Runs a callback that ends in link_required and returns the link token.
     */
    private function linkToken(User $user): string
    {
        $this->fakeProviderUser('google', 'g-1', $user->email, ['email_verified' => false]);
        $this->storeState('google');

        return $this->oauthCallback('google')->assertOk()->json('link_token');
    }

    public function test_confirm_link_with_the_password_links_the_identity_and_logs_in(): void
    {
        $user = User::factory()->create();
        $linkToken = $this->linkToken($user);

        $response = $this->withBearer($linkToken)
            ->postJson('/api/auth/oauth/google/link/confirm', [
                'password' => UserFactory::PASSWORD,
                'device_name' => 'Laptop',
            ])
            ->assertOk()
            ->assertJsonStructure(self::LOGIN_SUCCESS_KEYS)
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('oauth_identities', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g-1',
        ]);
        $this->assertDatabaseHas('devices', ['id' => $response->json('device_id'), 'device_name' => 'Laptop']);

        // The link token is single use.
        $this->assertSame(1, $user->tokens()->count());
        $this->withBearer($linkToken)
            ->postJson('/api/auth/oauth/google/link/confirm', [
                'password' => UserFactory::PASSWORD,
                'device_name' => 'Laptop',
            ])
            ->assertUnauthorized();
    }

    public function test_confirm_link_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->withBearer($this->linkToken($user))
            ->postJson('/api/auth/oauth/google/link/confirm', [
                'password' => 'wrong-password',
                'device_name' => 'Laptop',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_confirm_link_fails_without_a_pending_link_for_that_provider(): void
    {
        $user = User::factory()->create();

        $this->withBearer($this->linkToken($user))
            ->postJson('/api/auth/oauth/github/link/confirm', [
                'password' => UserFactory::PASSWORD,
                'device_name' => 'Laptop',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['oauth']);
    }

    // ---------------------------------------------------------------- unlink

    public function test_an_identity_can_be_unlinked_when_another_sign_in_method_remains(): void
    {
        $user = User::factory()->create();
        $github = $this->identityFor($user, 'github', 'gh-1');
        $this->identityFor($user, 'google', 'g-1');

        $this->withBearer($this->tokenFor($user))
            ->deleteJson("/api/auth/oauth/identities/{$github->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('oauth_identities', ['id' => $github->id]);
        $this->assertSame(1, $user->oauthIdentities()->count());
    }

    public function test_the_last_identity_can_be_unlinked_after_a_password_was_set(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $identity = $this->identityFor($user);

        $this->withBearer($this->tokenFor($user))
            ->deleteJson("/api/auth/oauth/identities/{$identity->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('oauth_identities', 0);
    }

    public function test_the_last_identity_can_be_unlinked_by_a_user_who_registered_with_a_password(): void
    {
        $this->postJson('/api/auth/register/start', [
            'email' => 'fresh@example.com',
            'password' => 'Str0ng-passw0rd!',
            'password_confirmation' => 'Str0ng-passw0rd!',
        ])->assertCreated();

        $user = User::where('email', 'fresh@example.com')->firstOrFail();
        $user->update(['name' => 'Fresh', 'tag' => 'fresh_user', 'status' => UserStatus::Active]);
        $identity = $this->identityFor($user);

        $this->withBearer($this->tokenFor($user))
            ->deleteJson("/api/auth/oauth/identities/{$identity->id}")
            ->assertNoContent();
    }

    public function test_the_last_identity_cannot_be_unlinked_by_a_user_created_through_oauth(): void
    {
        $this->fakeProviderUser('github', 'gh-new', 'fresh@example.com');
        $this->storeState('github');
        $this->oauthCallback('github')->assertOk();

        $user = User::where('email', 'fresh@example.com')->firstOrFail();
        $user->update(['name' => 'Fresh', 'tag' => 'fresh_user', 'status' => UserStatus::Active]);

        $this->withBearer($this->tokenFor($user))
            ->deleteJson("/api/auth/oauth/identities/{$user->oauthIdentities()->sole()->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identity']);
    }

    public function test_the_last_identity_cannot_be_unlinked_without_a_password_change_on_record(): void
    {
        $user = User::factory()->create();
        $identity = $this->identityFor($user);

        $this->withBearer($this->tokenFor($user))
            ->deleteJson("/api/auth/oauth/identities/{$identity->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identity']);

        $this->assertDatabaseHas('oauth_identities', ['id' => $identity->id]);
    }

    public function test_another_users_identity_cannot_be_unlinked(): void
    {
        $user = User::factory()->create();
        $foreign = $this->identityFor(User::factory()->create());

        $this->withBearer($this->tokenFor($user))
            ->deleteJson("/api/auth/oauth/identities/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('oauth_identities', ['id' => $foreign->id]);
    }

    public function test_a_malformed_identity_id_is_not_found(): void
    {
        $this->withBearer($this->tokenFor(User::factory()->create()))
            ->deleteJson('/api/auth/oauth/identities/123')
            ->assertNotFound();
    }
}
