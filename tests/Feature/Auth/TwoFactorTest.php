<?php

namespace Tests\Feature\Auth;

use App\Enums\TokenAbility;
use App\Models\User;
use Database\Factories\UserFactory;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use InteractsWithAuth;

    private const RECOVERY_CODE = 'ABCDE-FGHJK';

    /**
     * Password step of a login for a user with 2FA; returns the challenge token.
     */
    private function challenge(User $user): string
    {
        return $this->withoutBearer()
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->json('challenge_token');
    }

    // ------------------------------------------------------------ enrollment

    public function test_enable_returns_a_secret_and_provisioning_uri(): void
    {
        $user = User::factory()->create();

        $response = $this->withBearer($this->tokenFor($user))
            ->postJson('/api/auth/2fa/enable')
            ->assertOk()
            ->assertJsonStructure(['secret', 'qr_uri']);

        $this->assertStringStartsWith('otpauth://totp/', $response->json('qr_uri'));
        $this->assertStringContainsString('secret='.$response->json('secret'), $response->json('qr_uri'));

        // Not active until confirmed.
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_with_a_valid_code_enables_two_factor_and_returns_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->withBearer($this->tokenFor($user));

        $secret = $this->postJson('/api/auth/2fa/enable')->json('secret');

        $codes = $this->postJson('/api/auth/2fa/confirm', ['otp' => $this->currentTotp($secret)])
            ->assertOk()
            ->assertJsonStructure(['recovery_codes'])
            ->json('recovery_codes');

        $this->assertCount(8, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $code);
        }

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertSame(8, $user->recoveryCodes()->count());

        // Only hashes are stored.
        $this->assertDatabaseMissing('recovery_codes', ['code_hash' => $codes[0]]);

        // Fresh request: the guard still holds the user loaded before enrollment.
        $this->resetAuth()->getJson('/api/auth/me')->assertJsonPath('data.twoFactorEnabled', true);
    }

    public function test_confirm_rejects_a_wrong_code(): void
    {
        $user = User::factory()->create();
        $this->withBearer($this->tokenFor($user));

        $secret = $this->postJson('/api/auth/2fa/enable')->json('secret');
        $wrong = $this->currentTotp($secret) === '000000' ? '000001' : '000000';

        $this->postJson('/api/auth/2fa/confirm', ['otp' => $wrong])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['otp']);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_without_a_pending_enrollment_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->withBearer($this->tokenFor($user))
            ->postJson('/api/auth/2fa/confirm', ['otp' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['two_factor']);
    }

    public function test_enable_is_rejected_when_already_enabled(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $this->withBearer($this->tokenFor($user))
            ->postJson('/api/auth/2fa/enable')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['two_factor']);
    }

    // ----------------------------------------------------------------- login

    public function test_login_with_two_factor_returns_a_challenge_instead_of_a_session(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonStructure(self::TWO_FACTOR_REQUIRED_KEYS)
            ->assertJsonMissingPath('token')
            ->assertJsonPath('status', 'two_factor_required')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 300);

        $this->assertSame(0, $user->devices()->count());

        $token = $user->tokens()->sole();
        $this->assertSame([TokenAbility::TwoFactorPending->value], $token->abilities);
        $this->assertNotNull($token->expires_at);
    }

    public function test_verify_with_a_totp_code_completes_the_login(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $challenge = $this->challenge($user);

        $response = $this->withBearer($challenge)
            ->postJson('/api/auth/2fa/verify', [
                'code' => $this->currentTotp($secret),
                'device_name' => 'Phone',
            ])
            ->assertOk()
            ->assertJsonStructure(self::LOGIN_SUCCESS_KEYS)
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.twoFactorEnabled', true);

        $this->assertDatabaseHas('devices', ['id' => $response->json('device_id'), 'device_name' => 'Phone']);

        // The challenge token is consumed; only the session token remains.
        $this->assertSame(1, $user->tokens()->count());
        $this->withBearer($challenge)->postJson('/api/auth/2fa/verify', [
            'code' => '123456',
            'device_name' => 'Phone',
        ])->assertUnauthorized();

        $this->withBearer($response->json('token'))->getJson('/api/auth/me')->assertOk();
    }

    public function test_verify_rejects_a_wrong_code(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $wrong = $this->currentTotp($secret) === '000000' ? '000001' : '000000';

        $this->withBearer($this->challenge($user))
            ->postJson('/api/auth/2fa/verify', ['code' => $wrong, 'device_name' => 'Phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertSame(0, $user->devices()->count());
    }

    public function test_a_totp_code_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $code = $this->currentTotp($secret);

        $this->withBearer($this->challenge($user))
            ->postJson('/api/auth/2fa/verify', ['code' => $code, 'device_name' => 'Phone'])
            ->assertOk();

        $this->withBearer($this->challenge($user))
            ->postJson('/api/auth/2fa/verify', ['code' => $code, 'device_name' => 'Phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_verify_with_a_recovery_code_works_once(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $this->addRecoveryCode($user, self::RECOVERY_CODE);

        // Lower case and without the dash is accepted.
        $this->withBearer($this->challenge($user))
            ->postJson('/api/auth/2fa/verify', ['recovery_code' => 'abcdefghjk', 'device_name' => 'Phone'])
            ->assertOk()
            ->assertJsonStructure(self::LOGIN_SUCCESS_KEYS)
            ->assertJsonPath('status', 'authenticated');

        $this->assertNotNull($user->recoveryCodes()->sole()->used_at);

        $this->withBearer($this->challenge($user))
            ->postJson('/api/auth/2fa/verify', ['recovery_code' => self::RECOVERY_CODE, 'device_name' => 'Phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recovery_code']);
    }

    public function test_verify_requires_a_code_or_a_recovery_code(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $this->withBearer($this->challenge($user))
            ->postJson('/api/auth/2fa/verify', ['device_name' => 'Phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'recovery_code']);
    }

    // --------------------------------------------------------------- disable

    public function test_disable_requires_the_password(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $this->withBearer($this->tokenFor($user))
            ->postJson('/api/auth/2fa/disable', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disable_removes_the_secret_and_signs_out_other_sessions(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $this->addRecoveryCode($user, self::RECOVERY_CODE);

        $current = $this->tokenFor($user);
        $other = $this->tokenFor($user);

        $this->withBearer($current)
            ->postJson('/api/auth/2fa/disable', ['password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseCount('two_factor_secrets', 0);
        $this->assertDatabaseCount('recovery_codes', 0);

        $this->withBearer($current)->getJson('/api/auth/me')->assertOk();
        $this->withBearer($other)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_disable_is_rejected_when_not_enabled(): void
    {
        $user = User::factory()->create();

        $this->withBearer($this->tokenFor($user))
            ->postJson('/api/auth/2fa/disable', ['password' => UserFactory::PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['two_factor']);
    }

    // -------------------------------------------------------- recovery codes

    public function test_recovery_codes_can_be_regenerated_with_the_password(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $this->addRecoveryCode($user, self::RECOVERY_CODE);
        $oldId = $user->recoveryCodes()->sole()->id;

        $codes = $this->withBearer($this->tokenFor($user))
            ->postJson('/api/auth/2fa/recovery-codes', ['password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertJsonStructure(['recovery_codes'])
            ->json('recovery_codes');

        $this->assertCount(8, $codes);
        $this->assertSame(8, $user->recoveryCodes()->count());
        $this->assertDatabaseMissing('recovery_codes', ['id' => $oldId]);
    }

    public function test_regenerating_recovery_codes_requires_the_password(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $this->withBearer($this->tokenFor($user))
            ->postJson('/api/auth/2fa/recovery-codes', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
