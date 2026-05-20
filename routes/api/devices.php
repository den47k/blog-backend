<?php

use App\Http\Controllers\Auth\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth/devices')->group(function () {
    Route::get('/', [DeviceController::class, 'index']);
    Route::patch('{id}', [DeviceController::class, 'update']);
    Route::delete('{id}', [DeviceController::class, 'destroy']);
});
