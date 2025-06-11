<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['web']], function () {});


/* Auth routes */
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->name('verification.verify');

Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->name('verification.resend');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn(Request $request) => $request->user());
});



/* Test routes */
Route::get('/test', function () {
    return response()->json([
        'message' => 'test message',
        'time' => now()->toDateTimeString()
    ]);
});

Route::post('/test', function () {
    return response()->json([
        'message' => 'test message',
        'time' => now()->toDateTimeString()
    ]);
});
