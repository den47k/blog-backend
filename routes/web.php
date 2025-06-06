<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(403);
});

Route::get('/test', function () {
    dd('huy');
});
