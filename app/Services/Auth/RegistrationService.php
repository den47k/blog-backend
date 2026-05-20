<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public const REGISTER_TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly OtpService $otpService,
        private readonly TokenService $tokenService,
    ) {}

    public function start(string $email, string $password, ?string $ip = null): array
    {
        $user = DB::transaction(function () use ($email, $password) {
            $existing = $this->userRepository->findByEmailAnyStatus($email);

            if ($existing && $existing->status === UserStatus::Active) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered.'],
                ]);
            }

            if ($existing) {
                return $this->userRepository->update($existing, [
                    'name' => null,
                    'tag' => null,
                    'password' => Hash::make($password),
                    'status' => UserStatus::PendingEmail,
                    'email_verified_at' => null,
                ]);
            }

            return $this->userRepository->create([
                'email' => $email,
                'password' => Hash::make($password),
                'status' => UserStatus::PendingEmail,
            ]);
        });

        $code = $this->otpService->issue($email, OtpPurpose::Register, $ip);
        $user->notify(new EmailOtpNotification($code, OtpPurpose::Register->value));

        $token = $this->tokenService->issueToken(
            $user,
            'registration',
            [TokenAbility::RegisterPending->value],
            Carbon::now()->addMinutes(self::REGISTER_TOKEN_TTL_MINUTES),
        );

        return [
            'user' => $user,
            'token' => $token,
            'expires_in' => self::REGISTER_TOKEN_TTL_MINUTES * 60,
        ];
    }

    public function verifyEmail(User $user, string $otp): void
    {
        if ($user->status !== UserStatus::PendingEmail) {
            throw ValidationException::withMessages([
                'status' => ['Email already verified or not in verification step.'],
            ]);
        }

        $this->otpService->verify($user->email, OtpPurpose::Register, $otp);

        DB::transaction(function () use ($user) {
            $this->userRepository->markEmailVerified($user);
            $this->userRepository->update($user, ['status' => UserStatus::PendingProfile]);
        });

        event(new Verified($user));
    }

    public function resendOtp(User $user, ?string $ip = null): void
    {
        if ($user->status !== UserStatus::PendingEmail) {
            throw ValidationException::withMessages([
                'status' => ['No verification in progress.'],
            ]);
        }

        $code = $this->otpService->issue($user->email, OtpPurpose::Register, $ip);
        $user->notify(new EmailOtpNotification($code, OtpPurpose::Register->value));
    }

    public function completeProfile(User $user, string $name, string $tag): User
    {
        if ($user->status !== UserStatus::PendingProfile) {
            throw ValidationException::withMessages([
                'status' => ['Profile setup not allowed in current state.'],
            ]);
        }

        $registrationToken = $user->currentAccessToken();

        $updated = DB::transaction(fn () => $this->userRepository->update($user, [
            'name' => $name,
            'tag' => $tag,
            'status' => UserStatus::Active,
        ]));

        $registrationToken?->delete();

        return $updated;
    }

    public function suggestTags(User $user): array
    {
        $base = $this->normalizeTagBase($user->name ?? Str::before($user->email, '@'));

        $candidates = collect([
            $base,
            $base.random_int(1, 99),
            $base.'_'.random_int(100, 999),
        ])->filter(fn ($c) => $this->isValidTag($c) && ! $this->tagTaken($c))->unique()->values();

        return $candidates->take(3)->all();
    }

    private function normalizeTagBase(string $input): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $input);
        $clean = ltrim($clean, '0123456789_');

        if ($clean === '' || ! preg_match('/^[a-zA-Z]/', $clean)) {
            $clean = 'user'.$clean;
        }

        return Str::limit($clean, 24, '');
    }

    private function isValidTag(string $tag): bool
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $tag);
    }

    private function tagTaken(string $tag): bool
    {
        return DB::table('users')->where('tag', $tag)->exists();
    }
}
