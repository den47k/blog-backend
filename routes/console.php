<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('auth:prune-stale-devices')->daily();
Schedule::command('auth:prune-expired-email-otps')->daily();
Schedule::command('users:prune')->daily();
