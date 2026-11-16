<?php

namespace App\Services\Auth;

use App\Events\Auth\AuthAudited;
use App\Models\User;
use App\Repositories\Eloquent\TwoFactorRepository;
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
        private readonly TwoFactorRepository $twoFactorRepository,
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

        $this->twoFactorRepository->upsertSecret($user, $secret);

        return [
            'secret' => $secret,
            'qr_uri' => $this->buildQrUri($user->email, $secret),
        ];
    }

    public function confirmEnrollment(User $user, string $otp): array
    {
        $row = $this->twoFactorRepository->pendingSecret($user);

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
            $this->twoFactorRepository->confirmSecret($row, is_int($matched) ? $matched : null);

            return ['recovery_codes' => $this->generateRecoveryCodes($user)];
        });

        event(new AuthAudited('two_factor.enabled', $user));

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
            $this->twoFactorRepository->deleteSecret($user);
            $this->twoFactorRepository->deleteRecoveryCodes($user);
        });

        $this->tokenService->revokeAllExcept($user, $currentTokenId);

        event(new AuthAudited('two_factor.disabled', $user));
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

        $this->twoFactorRepository->recordSecretUse(
            $row,
            is_int($matched) ? $matched : $row->last_used_timestep,
        );
    }

    private function consumeRecoveryCode(User $user, string $recoveryCode): void
    {
        $candidate = $this->normalizeRecoveryCode($recoveryCode);

        $codes = $this->twoFactorRepository->unusedRecoveryCodes($user);

        foreach ($codes as $row) {
            if (Hash::check($candidate, $row->code_hash)) {
                $this->twoFactorRepository->markRecoveryCodeUsed($row);

                return;
            }
        }

        throw ValidationException::withMessages([
            'recovery_code' => ['Invalid recovery code.'],
        ]);
    }

    private function generateRecoveryCodes(User $user): array
    {
        $plaintext = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $plaintext[] = $this->randomRecoveryCode();
        }

        $this->twoFactorRepository->replaceRecoveryCodes(
            $user,
            array_map(fn (string $code) => Hash::make($code), $plaintext),
        );

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
