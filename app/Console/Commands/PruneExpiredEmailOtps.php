<?php

namespace App\Console\Commands;

use App\Repositories\EmailOtpRepository;
use Illuminate\Console\Command;

class PruneExpiredEmailOtps extends Command
{
    protected $signature = 'auth:prune-expired-email-otps {--hours=24}';

    protected $description = 'Delete expired or consumed email otps past the retention window.';

    public function handle(EmailOtpRepository $otpRepository): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $count = $otpRepository->pruneOlderThan(now()->subHours($hours));

        $this->info($count === 0 ? 'No expired otps.' : "Pruned {$count} expired email otps.");

        return self::SUCCESS;
    }
}
