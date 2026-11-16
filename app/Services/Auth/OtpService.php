<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Repositories\Eloquent\EmailOtpRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const MAX_ATTEMPTS = 5;

    public const TTL_MINUTES = 10;

    public function __construct(
        private readonly EmailOtpRepository $otpRepository,
    ) {}

    public function issue(string $email, OtpPurpose $purpose, ?string $ip = null): string
    {
        $this->invalidatePending($email, $purpose);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->otpRepository->create([
            'email' => $email,
            'purpose' => $purpose->value,
            'code_hash' => Hash::make($otp),
            'expires_at' => Carbon::now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => $ip,
        ]);

        return $otp;
    }

    public function verify(string $email, OtpPurpose $purpose, string $otp): void
    {
        $row = $this->otpRepository->latestPending($email, $purpose);

        if (! $row) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid or has expired.'],
            ]);
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            $this->otpRepository->consume($row);

            throw ValidationException::withMessages([
                'otp' => ['Too many failed attempts. Request a new code.'],
            ]);
        }

        if (! Hash::check($otp, $row->code_hash)) {
            $this->otpRepository->incrementAttempts($row);

            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid.'],
            ]);
        }

        $this->otpRepository->consume($row);
    }

    public function invalidatePending(string $email, OtpPurpose $purpose): void
    {
        $this->otpRepository->invalidatePending($email, $purpose);
    }
}
