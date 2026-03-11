<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->group(function () {
    Route::get('/search', [UserController::class, 'search'])->name('users.search');
    Route::put('/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/{user}/avatar', [UserController::class, 'deleteAvatar'])->name('users.deleteAvatar');
});
