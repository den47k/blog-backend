<?php

namespace App\Repositories;

use App\Enums\OtpPurpose;
use App\Models\EmailOtp;
use DateTimeInterface;

class EmailOtpRepository
{
    public function create(array $data): EmailOtp
    {
        return EmailOtp::create($data);
    }

    public function latestPending(string $email, OtpPurpose $purpose): ?EmailOtp
    {
        return EmailOtp::where('email', $email)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();
    }

    public function consume(EmailOtp $otp): void
    {
        $otp->forceFill(['consumed_at' => now()])->save();
    }

    public function incrementAttempts(EmailOtp $otp): void
    {
        $otp->increment('attempts');
    }

    public function invalidatePending(string $email, OtpPurpose $purpose): void
    {
        EmailOtp::where('email', $email)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    public function pruneOlderThan(DateTimeInterface $cutoff): int
    {
        return EmailOtp::where(function ($q) use ($cutoff) {
            $q->where('expires_at', '<', $cutoff)
                ->orWhere('consumed_at', '<', $cutoff);
        })->delete();
    }
}
