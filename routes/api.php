<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/* Auth routes */

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');

Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return new UserResource($request->user());
    });
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/users/search', [UserController::class, 'search'])->name('users.search');

    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversation.index');
    Route::post('/conversations/private', [ConversationController::class, 'createPrivateConversation'])->name('conversation.private');
    Route::post('/conversations/group', [ConversationController::class, 'createGroupConversation'])->name('conversation.group');
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
