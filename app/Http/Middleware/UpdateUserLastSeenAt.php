<?php

namespace App\Http\Middleware;

use App\Services\UserStatusService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class UpdateUserLastSeenAt
{
    public function __construct(private UserStatusService $userStatusService) {}

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $cacheKey = 'last-seen-update-lock' . Auth::id();

            if (!cache()->has($cacheKey)) {
                $this->userStatusService->updateLastSeen(Auth::id());
                cache()->put($cacheKey, true, now()->addSeconds(60));
            }
        }
        return $next($request);
    }
}
