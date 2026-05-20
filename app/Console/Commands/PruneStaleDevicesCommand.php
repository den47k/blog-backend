<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PruneStaleDevicesCommand extends Command
{
    protected $signature = 'auth:prune-stale-devices {--days=90}';

    protected $description = 'Delete devices (and their Sanctum tokens) inactive beyond the given window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $tokenIds = Device::where('created_at', '<', $cutoff)
            ->where(function ($q) use ($cutoff) {
                $q->where('last_seen_at', '<', $cutoff)
                    ->orWhereNull('last_seen_at');
            })
            ->pluck('personal_access_token_id');

        if ($tokenIds->isEmpty()) {
            $this->info('No stale devices.');

            return self::SUCCESS;
        }

        $count = PersonalAccessToken::whereIn('id', $tokenIds)->delete();

        $this->info("Pruned {$count} stale tokens (devices cascade-deleted).");

        return self::SUCCESS;
    }
}
