<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune {--unverified-hours=24} {--no-profile-days=7}';

    protected $description = "Delete users that haven't verified their email or finished their profile within the retention window.";

    public function handle(): int
    {
        $emailCutoff = now()->subHours(max(1, (int) $this->option('unverified-hours')));
        $profileCutoff = now()->subDays(max(1, (int) $this->option('no-profile-days')));

        $emailCount = $this->prune(UserStatus::PendingEmail, $emailCutoff);
        $profileCount = $this->prune(UserStatus::PendingProfile, $profileCutoff);

        $this->info("Pruned {$emailCount} unverified, {$profileCount} profile-incomplete users.");

        return self::SUCCESS;
    }

    private function prune(UserStatus $status, \DateTimeInterface $cutoff): int
    {
        $ids = User::where('status', $status)
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        PersonalAccessToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $ids)
            ->delete();

        return User::whereIn('id', $ids)->delete();
    }
}
