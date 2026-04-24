<?php

use App\Http\Controllers\Storage\StorageController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/mobile.php';

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    require __DIR__ . '/api/users.php';
    require __DIR__ . '/api/conversations.php';
    require __DIR__ . '/api/realtime.php';

    Route::get('/storage/{path}', StorageController::class)
        ->where('path', '.*')
        ->name('api.storage');
});

/* Test routes */
Route::get('/test', fn() => response()->json([
    'message' => 'test message',
    'time' => now()->toDateTimeString(),
]));

Route::post('/test', fn() => response()->json([
    'message' => 'test message',
    'time' => now()->toDateTimeString(),
]));
