<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('auth:prune-stale-devices')->daily();
Schedule::command('auth:prune-expired-email-otps')->daily();
Schedule::command('users:prune')->daily();
