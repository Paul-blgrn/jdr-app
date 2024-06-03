<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardPlayerController;
use Illuminate\Support\Facades\Auth;

if(app()->environment() === 'local') {
    Auth::loginUsingId(1);
}

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    // show all joined boards to the player
    Route::get('/boards', [BoardPlayerController::class, 'index']);

    // Show player requested board
    Route::get('/board/{board}', [BoardPlayerController::class, 'show']);

    // New player Join a board
    Route::post('/boards/join', [BoardPlayerController::class, 'store']);
});
