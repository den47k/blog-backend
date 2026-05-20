<?php

use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/2fa')->group(function () {
    Route::middleware(['auth:sanctum', 'abilities:2fa:pending', 'throttle:auth-2fa-verify'])->group(function () {
        Route::post('verify', [TwoFactorController::class, 'verify']);
    });

    Route::middleware(['auth:sanctum', 'abilities:*'])->group(function () {
        Route::post('enable', [TwoFactorController::class, 'enable']);
        Route::post('confirm', [TwoFactorController::class, 'confirm']);
        Route::post('disable', [TwoFactorController::class, 'disable']);
        Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });
});
