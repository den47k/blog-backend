<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use InteractsWithAuth;

    private const NEW_PASSWORD = 'a-brand-new-password';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * Requests a reset link and returns the token from the e-mail.
     */
    private function requestToken(User $user): string
    {
        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])->assertOk();

        return Notification::sent($user, ResetPassword::class)->last()->token;
    }

    // ---------------------------------------------------------------- forgot

    public function test_forgot_password_emails_a_link_to_the_web_app(): void
    {
        config(['app.frontend_url' => 'https://app.example.test/']);
        $user = User::factory()->create();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return $url === 'https://app.example.test/reset-password?token='.$notification->token
                .'&email='.urlencode($user->email);
        });
    }

    public function test_forgot_password_does_not_reveal_whether_the_email_exists(): void
    {
        $this->postJson('/api/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $this->postJson('/api/auth/password/forgot', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ----------------------------------------------------------------- reset

    public function test_reset_changes_the_password_and_revokes_every_session(): void
    {
        $user = User::factory()->create();
        $session = $this->tokenFor($user);
        $this->tokenFor($user);
        $token = $this->requestToken($user);

        $this->postJson('/api/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertSame(0, $user->tokens()->count());

        $this->withBearer($session)->getJson('/api/auth/me')->assertUnauthorized();

        $this->withoutBearer()
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::NEW_PASSWORD])
            ->assertOk();
    }

    public function test_a_reset_token_works_only_once(): void
    {
        $user = User::factory()->create();
        $payload = [
            'token' => $this->requestToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];

        $this->postJson('/api/auth/password/reset', $payload)->assertOk();

        $this->postJson('/api/auth/password/reset', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();
        $this->requestToken($user);

        $this->postJson('/api/auth/password/reset', [
            'token' => 'not-the-token',
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertTrue(Hash::check(UserFactory::PASSWORD, $user->fresh()->password));
    }

    public function test_reset_validates_the_new_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/password/reset', [
            'token' => $this->requestToken($user),
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
