<?php

namespace App\Console\Commands;

use App\Repositories\DeviceRepository;
use App\Services\Auth\TokenService;
use Illuminate\Console\Command;

class PruneStaleDevices extends Command
{
    protected $signature = 'auth:prune-stale-devices {--days=90}';

    protected $description = 'Delete devices (and their Sanctum tokens) inactive beyond the given window.';

    public function handle(DeviceRepository $deviceRepository, TokenService $tokenService): int
    {
        $days = max(1, (int) $this->option('days'));
        $tokenIds = $deviceRepository->staleTokenIds(now()->subDays($days));

        if ($tokenIds->isEmpty()) {
            $this->info('No stale devices.');

            return self::SUCCESS;
        }

        $count = $tokenService->revokeMany($tokenIds);

        $this->info("Pruned {$count} stale tokens (devices cascade-deleted).");

        return self::SUCCESS;
    }
}
