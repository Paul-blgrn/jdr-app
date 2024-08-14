<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::options('/{any}', function () {
    return response()->json(['message' => 'Preflight Request']);
})->where('any', '.*')->middleware(\App\Http\Middleware\CorsMiddleware::class);

Route::get('/auth/check', [AuthenticatedSessionController::class, 'checkAuth']);

Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
})->middleware(\Illuminate\Http\Middleware\HandleCors::class);

require __DIR__.'/auth.php';
