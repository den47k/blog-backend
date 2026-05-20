<?php

namespace App\Services\Auth;

use App\Events\Auth\TwoFactorDisabled;
use App\Events\Auth\TwoFactorEnabled;
use App\Models\RecoveryCode;
use App\Models\TwoFactorSecret;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public const RECOVERY_CODE_COUNT = 8;

    public const RECOVERY_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const TOTP_WINDOW = 1;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly TokenService $tokenService,
    ) {}

    public function startEnrollment(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is already enabled.'],
            ]);
        }

        $secret = $this->google2fa->generateSecretKey();

        TwoFactorSecret::updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => $secret,
                'confirmed_at' => null,
                'last_used_timestep' => null,
                'last_used_at' => null,
            ],
        );

        return [
            'secret' => $secret,
            'qr_uri' => $this->buildQrUri($user->email, $secret),
        ];
    }

    public function confirmEnrollment(User $user, string $otp): array
    {
        $row = TwoFactorSecret::where('user_id', $user->id)->whereNull('confirmed_at')->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'two_factor' => ['No pending 2FA enrollment found.'],
            ]);
        }

        $matched = $this->google2fa->verifyKey($row->secret, $otp, self::TOTP_WINDOW);

        if ($matched === false) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code.'],
            ]);
        }

        $result = DB::transaction(function () use ($row, $matched, $user) {
            $row->forceFill([
                'confirmed_at' => now(),
                'last_used_timestep' => is_int($matched) ? $matched : null,
                'last_used_at' => now(),
            ])->save();

            return ['recovery_codes' => $this->generateRecoveryCodes($user)];
        });

        event(new TwoFactorEnabled($user));

        return $result;
    }

    public function verifyForLogin(User $user, ?string $code, ?string $recoveryCode): void
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        if ($code !== null && $code !== '') {
            $this->verifyTotp($user, $code);

            return;
        }

        if ($recoveryCode !== null && $recoveryCode !== '') {
            $this->consumeRecoveryCode($user, $recoveryCode);

            return;
        }

        throw ValidationException::withMessages([
            'code' => ['Provide either a verification code or a recovery code.'],
        ]);
    }

    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        $currentTokenId = $user->currentAccessToken()?->id;

        DB::transaction(function () use ($user) {
            TwoFactorSecret::where('user_id', $user->id)->delete();
            RecoveryCode::where('user_id', $user->id)->delete();
        });

        $this->tokenService->revokeAllExcept($user, $currentTokenId);

        event(new TwoFactorDisabled($user));
    }

    public function regenerateRecoveryCodes(User $user, string $password): array
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        if (! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        return DB::transaction(fn () => $this->generateRecoveryCodes($user));
    }

    private function verifyTotp(User $user, string $code): void
    {
        $row = $user->twoFactorSecret;

        $matched = $this->google2fa->verifyKeyNewer(
            $row->secret,
            $code,
            $row->last_used_timestep ?? 0,
            self::TOTP_WINDOW,
        );

        if ($matched === false) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or replayed verification code.'],
            ]);
        }

        $row->forceFill([
            'last_used_timestep' => is_int($matched) ? $matched : $row->last_used_timestep,
            'last_used_at' => now(),
        ])->save();
    }

    private function consumeRecoveryCode(User $user, string $recoveryCode): void
    {
        $candidate = $this->normalizeRecoveryCode($recoveryCode);

        $codes = RecoveryCode::where('user_id', $user->id)->whereNull('used_at')->get();

        foreach ($codes as $row) {
            if (Hash::check($candidate, $row->code_hash)) {
                $row->forceFill(['used_at' => now()])->save();

                return;
            }
        }

        throw ValidationException::withMessages([
            'recovery_code' => ['Invalid recovery code.'],
        ]);
    }

    private function generateRecoveryCodes(User $user): array
    {
        RecoveryCode::where('user_id', $user->id)->delete();

        $plaintext = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = $this->randomRecoveryCode();
            $plaintext[] = $code;

            RecoveryCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
            ]);
        }

        return $plaintext;
    }

    private function randomRecoveryCode(): string
    {
        $alphabet = self::RECOVERY_CODE_ALPHABET;
        $len = strlen($alphabet);
        $raw = '';

        for ($i = 0; $i < 10; $i++) {
            $raw .= $alphabet[random_int(0, $len - 1)];
        }

        return substr($raw, 0, 5).'-'.substr($raw, 5, 5);
    }

    private function normalizeRecoveryCode(string $input): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));

        if (strlen($clean) === 10) {
            return substr($clean, 0, 5).'-'.substr($clean, 5, 5);
        }

        return $input;
    }

    private function buildQrUri(string $email, string $secret): string
    {
        $issuer = config('app.name', 'Laravel');

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
        );
    }
}
