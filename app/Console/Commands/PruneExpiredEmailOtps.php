<?php

namespace App\Console\Commands;

use App\Models\EmailOtp;
use Illuminate\Console\Command;

class PruneExpiredEmailOtps extends Command
{
    protected $signature = 'auth:prune-expired-email-otps {--hours=24}';

    protected $description = 'Delete expired or consumed email otps past the retention window.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $count = EmailOtp::where(function ($q) use ($cutoff) {
            $q->where('expires_at', '<', $cutoff)
                ->orWhere('consumed_at', '<', $cutoff);
        })->delete();

        $this->info($count === 0 ? 'No expired otps.' : "Pruned {$count} expired email otps.");
        return self::SUCCESS;
    }
}
