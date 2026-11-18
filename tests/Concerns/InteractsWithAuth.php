<?php

namespace Tests\Concerns;

use App\Enums\TokenAbility;
use App\Models\RecoveryCode;
use App\Models\TwoFactorSecret;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

/**
 * Helpers for tests that authenticate with real Sanctum bearer tokens
 * (needed whenever the code under test reads or deletes the current token).
 *
 * JSON shapes mirror wisp-app/packages/domain/src/{auth,user}.ts.
 */
trait InteractsWithAuth
{
    protected const AUTHENTICATED_USER_KEYS = [
        'id', 'name', 'tag', 'avatar', 'email', 'isEmailVerified', 'twoFactorEnabled',
    ];

    /** LoginSuccessSchema */
    protected const LOGIN_SUCCESS_KEYS = [
        'status',
        'user' => self::AUTHENTICATED_USER_KEYS,
        'token',
        'token_type',
        'device_id',
    ];

    /** Login2FARequiredSchema */
    protected const TWO_FACTOR_REQUIRED_KEYS = ['status', 'challenge_token', 'token_type', 'expires_in'];

    /**
     * Sends the following requests with the given bearer token.
     */
    protected function withBearer(string $token): static
    {
        $this->resetAuth();

        return $this->withToken($token);
    }

    /**
     * Sends the following requests without credentials.
     */
    protected function withoutBearer(): static
    {
        $this->resetAuth();

        return $this->flushHeaders();
    }

    /**
     * Issues a token directly, bypassing the login endpoint (no device row).
     *
     * @param  list<TokenAbility>  $abilities
     */
    protected function tokenFor(User $user, array $abilities = [TokenAbility::Full], ?Carbon $expiresAt = null): string
    {
        return $user->createToken(
            'test',
            array_map(fn (TokenAbility $ability) => $ability->value, $abilities),
            $expiresAt,
        )->plainTextToken;
    }

    /**
     * Logs in through the API, which also registers a device.
     *
     * @return array{token: string, device_id: string}
     */
    protected function login(User $user, string $deviceName = 'Test browser'): array
    {
        $response = $this->withoutBearer()->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => UserFactory::PASSWORD,
            'device_name' => $deviceName,
        ])->assertOk();

        return ['token' => $response->json('token'), 'device_id' => $response->json('device_id')];
    }

    /**
     * Gives the user a confirmed TOTP secret that has never been used.
     */
    protected function enableTwoFactor(User $user): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        TwoFactorSecret::create([
            'user_id' => $user->id,
            'secret' => $secret,
            'confirmed_at' => now(),
        ]);

        return $secret;
    }

    protected function currentTotp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    protected function addRecoveryCode(User $user, string $code): void
    {
        RecoveryCode::create(['user_id' => $user->id, 'code_hash' => Hash::make($code)]);
    }

    /**
     * The code from the most recent OTP e-mail sent to the user.
     * Requires Notification::fake().
     */
    protected function lastEmailOtp(User $user): string
    {
        $sent = Notification::sent($user, EmailOtpNotification::class);

        $this->assertNotEmpty($sent, 'No OTP e-mail was sent.');

        return $sent->last()->code;
    }
}
