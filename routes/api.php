<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'application' => config('app.name', 'AssessorGov IA'),
        'status' => 'ok',
    ]);
});
