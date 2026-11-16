<?php

namespace Tests\Feature\Auth;

use App\Models\Device;
use App\Models\User;
use Database\Factories\UserFactory;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithAuth;

    private const FIREFOX_ON_LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0';

    // ----------------------------------------------------------------- login

    public function test_login_returns_a_token_and_registers_a_device(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('User-Agent', self::FIREFOX_ON_LINUX)
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => UserFactory::PASSWORD,
                'device_name' => 'Work laptop',
            ])
            ->assertOk()
            ->assertJsonStructure(self::LOGIN_SUCCESS_KEYS)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.isEmailVerified', true)
            ->assertJsonPath('user.twoFactorEnabled', false);

        $device = Device::findOrFail($response->json('device_id'));

        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('Work laptop', $device->device_name);
        $this->assertSame('web', $device->client_type);
        $this->assertSame('Linux', $device->platform);
        $this->assertSame('Firefox', $device->browser);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_device_name_defaults_to_web(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => UserFactory::PASSWORD,
        ])->assertOk();

        $this->assertSame('web', Device::findOrFail($response->json('device_id'))->device_name);
    }

    public function test_the_issued_token_authenticates_api_requests(): void
    {
        $user = User::factory()->create();
        $session = $this->login($user);

        $this->withBearer($session['token'])
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure(['data' => self::AUTHENTICATED_USER_KEYS])
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_rejects_an_unknown_email(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_reports_an_unfinished_registration(): void
    {
        $user = User::factory()->pendingProfile()->create();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertForbidden()
            ->assertJsonStructure(['message', 'status'])
            ->assertJsonPath('status', 'pending_profile');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_is_throttled_after_five_attempts_per_minute(): void
    {
        $user = User::factory()->create();
        $attempt = fn () => $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $attempt()->assertUnprocessable();
        }

        $attempt()->assertTooManyRequests();
    }

    // ---------------------------------------------------------------- logout

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $first = $this->login($user, 'First');
        $second = $this->login($user, 'Second');

        $this->withBearer($first['token'])
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->withBearer($first['token'])->getJson('/api/user')->assertUnauthorized();
        $this->withBearer($second['token'])->getJson('/api/user')->assertOk();

        // The device goes away with its token.
        $this->assertDatabaseMissing('devices', ['id' => $first['device_id']]);
        $this->assertDatabaseHas('devices', ['id' => $second['device_id']]);
    }

    public function test_logout_all_revokes_every_token(): void
    {
        $user = User::factory()->create();
        $first = $this->login($user, 'First');
        $second = $this->login($user, 'Second');

        $this->withBearer($first['token'])->postJson('/api/auth/logout/all')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, $user->devices()->count());
        $this->withBearer($second['token'])->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
