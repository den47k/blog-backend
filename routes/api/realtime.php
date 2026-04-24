<?php

use App\Http\Controllers\Realtime\RealtimeTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('realtime')->group(function () {
    Route::post('/token', [RealtimeTokenController::class, 'connect'])->name('realtime.token');
    Route::post('/subscribe', [RealtimeTokenController::class, 'subscribe'])->name('realtime.subscribe');
});
