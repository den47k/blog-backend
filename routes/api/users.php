<?php

use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->group(function () {
    Route::get('/search', [UserController::class, 'search'])->middleware('throttle:search')->name('users.search');
    Route::patch('/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/{user}/avatar', [UserController::class, 'updateAvatar'])->name('users.updateAvatar');
    Route::delete('/{user}/avatar', [UserController::class, 'deleteAvatar'])->name('users.deleteAvatar');
});
