<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/* Guest routes */
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

Route::post('/forgot-password', [ResetPasswordController::class, 'requestResetLink'])->name('password.email');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return new UserResource($request->user());
    });
});

/* Auth */
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('/search', [UserController::class, 'search'])->name('users.search');
        Route::post('/{user}/avatar', [UserController::class, 'uploadAvatar'])->name('users.uploadAvatar');
        Route::delete('/{user}/avatar', [UserController::class, 'deleteAvatar'])->name('users.deleteAvatar');
    });
    
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index'])->name('conversation.index');
        Route::get('/private/{tag}', [ConversationController::class, 'show'])->name('conversation.private.show');
        Route::post('/private', [ConversationController::class, 'createPrivateConversation'])->name('conversation.private');
        // Route::post('/group', [ConversationController::class, 'createGroupConversation'])->name('conversation.group');
        Route::post('/{conversation:id}/mark-as-read', [ConversationController::class, 'markAsRead'])->name('conversation.markAsRead');

        Route::get('/{conversation:id}/messages', [MessageController::class, 'index'])->name('conversation.messages.index');
        Route::post('/{conversation:id}/messages', [MessageController::class, 'store'])->name('conversation.messages.store');
        Route::patch('/{conversation:id}/messages/{message:id}', [MessageController::class, 'update'])->name('conversation.messages.update');
        Route::delete('/{conversation:id}/messages/{message:id}', [MessageController::class, 'delete'])->name('conversation.messages.delete');
    });
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


// Route::get('/storage/{path}', function ($path) {
//     if (!Storage::disk('s3')->exists($path)) {
//         abort(404);
//     }

//     return response(
//         Storage::disk('s3')->get($path),
//         200,
//         ['Content-Type' => Storage::disk('s3')->mimeType($path)]
//     );
// })->where('path', '.*')->name('api.storage');


// Route::get('/storage/{path}', function ($path) {
//     if (!Storage::disk('s3')->exists($path)) {
//         abort(404);
//     }

//     $temp = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5));
//     Log::info($temp);

//     return response()->stream(function () use ($path) {
//         $stream = Storage::disk('s3')->readStream($path);
//         fpassthru($stream);
//         fclose($stream);
//     }, 200, [
//         'Content-Type' => Storage::disk('s3')->mimeType($path),
//     ]);
// })->where('path', '.*')->name('api.storage');
