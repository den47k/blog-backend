<?php

namespace App\Repositories\Eloquent;

use App\Models\RecoveryCode;
use App\Models\TwoFactorSecret;
use App\Models\User;
use Illuminate\Support\Collection;

class TwoFactorRepository
{
    /** Stores a new unconfirmed secret, replacing any earlier one. */
    public function upsertSecret(User $user, string $secret): TwoFactorSecret
    {
        return TwoFactorSecret::updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => $secret,
                'confirmed_at' => null,
                'last_used_timestep' => null,
                'last_used_at' => null,
            ],
        );
    }

    public function pendingSecret(User $user): ?TwoFactorSecret
    {
        return TwoFactorSecret::where('user_id', $user->id)
            ->whereNull('confirmed_at')
            ->first();
    }

    public function confirmSecret(TwoFactorSecret $secret, ?int $timestep): void
    {
        $secret->forceFill([
            'confirmed_at' => now(),
            'last_used_timestep' => $timestep,
            'last_used_at' => now(),
        ])->save();
    }

    public function recordSecretUse(TwoFactorSecret $secret, ?int $timestep): void
    {
        $secret->forceFill([
            'last_used_timestep' => $timestep,
            'last_used_at' => now(),
        ])->save();
    }

    public function deleteSecret(User $user): void
    {
        TwoFactorSecret::where('user_id', $user->id)->delete();
    }

    public function unusedRecoveryCodes(User $user): Collection
    {
        return RecoveryCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->get();
    }

    public function markRecoveryCodeUsed(RecoveryCode $code): void
    {
        $code->forceFill(['used_at' => now()])->save();
    }

    public function deleteRecoveryCodes(User $user): void
    {
        RecoveryCode::where('user_id', $user->id)->delete();
    }

    /**
     * @param  string[]  $codeHashes
     */
    public function replaceRecoveryCodes(User $user, array $codeHashes): void
    {
        $this->deleteRecoveryCodes($user);

        foreach ($codeHashes as $hash) {
            RecoveryCode::create([
                'user_id' => $user->id,
                'code_hash' => $hash,
            ]);
        }
    }
}
