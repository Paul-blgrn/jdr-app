<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BoardController;
use Illuminate\Support\Facades\Auth;

if(app()->environment() === 'local') {
    Auth::loginUsingId(1);
}

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth')->group(function () {
    // Boards
    Route::get('/boards', [BoardController::class, 'index']);
    Route::get('/board/{board}', [BoardController::class, 'show']);

    Route::post('/boards/join', [BoardController::class, 'join']);
});
