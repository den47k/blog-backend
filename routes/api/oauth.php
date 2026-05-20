<?php

use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/oauth')->group(function () {
    Route::get('{provider}/redirect', [OAuthController::class, 'redirect'])
        ->where('provider', 'google|github');
    Route::get('{provider}/callback', [OAuthController::class, 'callback'])
        ->where('provider', 'google|github');

    Route::middleware(['auth:sanctum', 'abilities:oauth:link:pending'])->group(function () {
        Route::post('{provider}/link/confirm', [OAuthController::class, 'confirmLink'])
            ->where('provider', 'google|github');
    });

    Route::middleware(['auth:sanctum', 'abilities:*'])->group(function () {
        Route::delete('identities/{id}', [OAuthController::class, 'unlink']);
    });
});
