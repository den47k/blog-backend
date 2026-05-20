<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Models\EmailOtp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const MAX_ATTEMPTS = 5;

    public const TTL_MINUTES = 10;

    public function issue(string $email, OtpPurpose $purpose, ?string $ip = null): string
    {
        $this->invalidatePending($email, $purpose);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailOtp::create([
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
        $row = EmailOtp::where('email', $email)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid or has expired.'],
            ]);
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            $row->forceFill(['consumed_at' => now()])->save();
            throw ValidationException::withMessages([
                'otp' => ['Too many failed attempts. Request a new code.'],
            ]);
        }

        if (! Hash::check($otp, $row->code_hash)) {
            $row->increment('attempts');
            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid.'],
            ]);
        }

        $row->forceFill(['consumed_at' => now()])->save();
    }

    public function invalidatePending(string $email, OtpPurpose $purpose): void
    {
        EmailOtp::where('email', $email)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
