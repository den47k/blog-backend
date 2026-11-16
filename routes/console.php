<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:prune-stale-devices')->daily();
Schedule::command('auth:prune-expired-email-otps')->daily();
Schedule::command('users:prune')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
