<?php

use App\Http\Controllers\Auth\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::post('/register', [MobileAuthController::class, 'register']);
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::post('/tokens/revoke-all', [MobileAuthController::class, 'revokeAllTokens']);
        Route::get('/tokens', [MobileAuthController::class, 'tokens']);
        Route::delete('/tokens/{token_id}', [MobileAuthController::class, 'revokeSpecificToken']);
    });
});
