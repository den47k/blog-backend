<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Resources\User\AuthenticatedUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth-burst')->group(function () {
    Route::post('/auth/login', [SessionController::class, 'login']);
    Route::post('/auth/register/start', [RegisterController::class, 'start']);
    Route::post('/forgot-password', [ResetPasswordController::class, 'requestResetLink'])->name('password.email');
});

Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::middleware(['auth:sanctum', 'abilities:register:pending'])->group(function () {
    Route::middleware('throttle:auth-otp')->group(function () {
        Route::post('/auth/register/verify-email', [RegisterController::class, 'verifyEmail']);
        Route::post('/auth/register/resend-otp', [RegisterController::class, 'resendOtp']);
    });
    Route::post('/auth/register/profile', [RegisterController::class, 'completeProfile']);
    Route::get('/auth/register/suggest-tag', [RegisterController::class, 'suggestTag']);
});

Route::middleware(['auth:sanctum', 'abilities:*'])->group(function () {
    Route::post('/auth/logout', [SessionController::class, 'logout']);
    Route::post('/auth/logout/all', [SessionController::class, 'logoutAll']);
    Route::get('/user', fn (Request $request) => new AuthenticatedUserResource($request->user()));
});
