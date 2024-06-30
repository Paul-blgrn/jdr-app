<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BoardController;
use App\Http\Controllers\PlayerBoardController;
use Illuminate\Support\Facades\Auth;

if(app()->environment() === 'local') {
    Auth::loginUsingId(1);
}

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    // -----------------
    // PlayerBoard Controller
    // -----------------

    // Display all the user Boards
    Route::get('/boards', [PlayerBoardController::class, 'index']);

    // Display Board with details for user
    Route::get('/board/{board}', [PlayerBoardController::class, 'show']);

    // Join a new Board
    Route::post('/boards/join', [PlayerBoardController::class, 'store']);

    // Leave a Board
    Route::delete('/board/leave/{board}', [PlayerBoardController::class,'destroy']);

    // -----------------
    // Board Controller
    // -----------------

    // Add a Board
    Route::post('/boards/add', [BoardController::class, 'store']);

    // Delete a Board
    Route::delete('/board/delete/{board}', [BoardController::class, 'destroy']);
});
