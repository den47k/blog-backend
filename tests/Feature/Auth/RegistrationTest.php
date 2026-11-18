<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use InteractsWithAuth;

    private const EMAIL = 'new.user@example.com';

    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * Runs step 1 and returns the registration token.
     */
    private function start(string $email = self::EMAIL): string
    {
        return $this->postJson('/api/auth/register/start', [
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertCreated()->json('registration_token');
    }

    private function user(string $email = self::EMAIL): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // ----------------------------------------------------------------- start

    public function test_start_creates_a_pending_user_and_emails_a_code(): void
    {
        $this->postJson('/api/auth/register/start', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])
            ->assertCreated()
            ->assertJsonStructure(['registration_token', 'token_type', 'expires_in', 'email'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800)
            ->assertJsonPath('email', self::EMAIL);

        $user = $this->user();

        $this->assertSame(UserStatus::PendingEmail, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->name);
        $this->assertNull($user->tag);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        Notification::assertSentTo($user, EmailOtpNotification::class);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->lastEmailOtp($user));
    }

    public function test_start_validates_input(): void
    {
        $this->postJson('/api/auth/register/start', [
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_start_rejects_an_email_that_belongs_to_an_active_user(): void
    {
        User::factory()->create(['email' => self::EMAIL]);

        $this->postJson('/api/auth/register/start', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_start_restarts_an_unfinished_registration(): void
    {
        $this->start();
        $firstCode = $this->lastEmailOtp($this->user());

        $token = $this->start();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(UserStatus::PendingEmail, $this->user()->status);

        // The code from the first attempt was invalidated by the second one.
        $this->withBearer($token)
            ->postJson('/api/auth/register/verify-email', ['otp' => $firstCode])
            ->assertUnprocessable();
    }

    // ---------------------------------------------------------- verify email

    public function test_verify_email_with_the_emailed_code_moves_to_the_profile_step(): void
    {
        $token = $this->start();
        $code = $this->lastEmailOtp($this->user());

        $this->withBearer($token)
            ->postJson('/api/auth/register/verify-email', ['otp' => $code])
            ->assertOk()
            ->assertExactJson(['status' => 'pending_profile']);

        $user = $this->user();

        $this->assertSame(UserStatus::PendingProfile, $user->status);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_rejects_a_wrong_code(): void
    {
        $token = $this->start();
        $code = $this->lastEmailOtp($this->user());
        $wrong = $code === '000000' ? '000001' : '000000';

        $this->withBearer($token)
            ->postJson('/api/auth/register/verify-email', ['otp' => $wrong])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);

        $this->assertSame(UserStatus::PendingEmail, $this->user()->status);
    }

    public function test_verify_email_requires_a_six_digit_code(): void
    {
        $token = $this->start();

        $this->withBearer($token)
            ->postJson('/api/auth/register/verify-email', ['otp' => '12ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_the_code_is_burned_after_five_wrong_attempts(): void
    {
        $token = $this->start();
        $code = $this->lastEmailOtp($this->user());
        $wrong = $code === '000000' ? '000001' : '000000';

        $this->withBearer($token);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register/verify-email', ['otp' => $wrong])->assertUnprocessable();
        }

        $this->postJson('/api/auth/register/verify-email', ['otp' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);

        $this->assertSame(UserStatus::PendingEmail, $this->user()->status);
    }

    public function test_verify_email_cannot_be_repeated(): void
    {
        $token = $this->start();
        $code = $this->lastEmailOtp($this->user());

        $this->withBearer($token);
        $this->postJson('/api/auth/register/verify-email', ['otp' => $code])->assertOk();

        $this->postJson('/api/auth/register/verify-email', ['otp' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_verify_email_requires_the_registration_token(): void
    {
        $this->postJson('/api/auth/register/verify-email', ['otp' => '123456'])->assertUnauthorized();
    }

    // ---------------------------------------------------------------- resend

    public function test_resend_sends_a_new_code_and_invalidates_the_old_one(): void
    {
        $token = $this->start();
        $user = $this->user();
        $oldCode = $this->lastEmailOtp($user);

        $this->withBearer($token)
            ->postJson('/api/auth/register/resend-otp')
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentToTimes($user, EmailOtpNotification::class, 2);
        $newCode = $this->lastEmailOtp($user);

        if ($newCode !== $oldCode) {
            $this->postJson('/api/auth/register/verify-email', ['otp' => $oldCode])->assertUnprocessable();
        }

        $this->postJson('/api/auth/register/verify-email', ['otp' => $newCode])->assertOk();
    }

    public function test_resend_is_rejected_once_the_email_is_verified(): void
    {
        $user = User::factory()->pendingProfile()->create();

        $this->withBearer($this->tokenFor($user, [\App\Enums\TokenAbility::RegisterPending]))
            ->postJson('/api/auth/register/resend-otp')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        Notification::assertNothingSent();
    }

    // --------------------------------------------------------------- profile

    /**
     * Runs steps 1 and 2 and returns the registration token.
     */
    private function startAndVerify(): string
    {
        $token = $this->start();

        $this->withBearer($token)
            ->postJson('/api/auth/register/verify-email', ['otp' => $this->lastEmailOtp($this->user())])
            ->assertOk();

        return $token;
    }

    public function test_completing_the_profile_activates_the_user_and_logs_them_in(): void
    {
        $registrationToken = $this->startAndVerify();

        $response = $this->postJson('/api/auth/register/profile', [
            'name' => 'New User',
            'tag' => 'new_user',
            'device_name' => 'Firefox on Linux',
        ])
            ->assertCreated()
            ->assertJsonStructure(self::LOGIN_SUCCESS_KEYS)
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.name', 'New User')
            ->assertJsonPath('user.tag', 'new_user')
            ->assertJsonPath('user.email', self::EMAIL)
            ->assertJsonPath('user.isEmailVerified', true)
            ->assertJsonPath('user.twoFactorEnabled', false);

        $user = $this->user();
        $this->assertSame(UserStatus::Active, $user->status);

        // A device is bound to the new token.
        $device = Device::findOrFail($response->json('device_id'));
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('Firefox on Linux', $device->device_name);

        // The registration token is single use; only the session token remains.
        $this->assertSame(1, $user->tokens()->count());
        $this->withBearer($registrationToken)->getJson('/api/user')->assertUnauthorized();

        $this->withBearer($response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_profile_validates_name_and_tag(): void
    {
        $this->startAndVerify();

        $this->postJson('/api/auth/register/profile', [
            'name' => 'ab',
            'tag' => '1bad tag',
            'device_name' => 'Browser',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'tag']);

        $this->assertSame(UserStatus::PendingProfile, $this->user()->status);
    }

    public function test_profile_rejects_a_taken_tag(): void
    {
        User::factory()->create(['tag' => 'taken_tag']);
        $this->startAndVerify();

        $this->postJson('/api/auth/register/profile', [
            'name' => 'New User',
            'tag' => 'taken_tag',
            'device_name' => 'Browser',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag']);
    }

    public function test_profile_cannot_be_completed_before_the_email_is_verified(): void
    {
        $token = $this->start();

        $this->withBearer($token)
            ->postJson('/api/auth/register/profile', [
                'name' => 'New User',
                'tag' => 'new_user',
                'device_name' => 'Browser',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame(UserStatus::PendingEmail, $this->user()->status);
    }

    // ----------------------------------------------------------- suggest tag

    public function test_suggest_tag_returns_valid_free_tags_based_on_the_email(): void
    {
        $token = $this->start('jane.doe@example.com');

        $suggestions = $this->withBearer($token)
            ->getJson('/api/auth/register/suggest-tag')
            ->assertOk()
            ->assertJsonStructure(['suggestions'])
            ->json('suggestions');

        $this->assertNotEmpty($suggestions);

        foreach ($suggestions as $tag) {
            $this->assertMatchesRegularExpression('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $tag);
            $this->assertStringStartsWith('jane_doe', $tag);
            $this->assertDatabaseMissing('users', ['tag' => $tag]);
        }
    }
}
