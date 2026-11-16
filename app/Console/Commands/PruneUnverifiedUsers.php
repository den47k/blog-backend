<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Auth\TokenService;
use Illuminate\Console\Command;

class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune {--unverified-hours=24} {--no-profile-days=7}';

    protected $description = "Delete users that haven't verified their email or finished their profile within the retention window.";

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TokenService $tokenService,
    ) {
        parent::__construct();
    }

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
        $ids = $this->userRepository->pendingOlderThan($status, $cutoff);

        if ($ids->isEmpty()) {
            return 0;
        }

        $this->tokenService->revokeAllForUsers($ids);

        return $this->userRepository->deleteMany($ids);
    }
}
