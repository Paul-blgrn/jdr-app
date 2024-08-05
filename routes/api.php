<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BoardController;
use App\Http\Controllers\PlayerBoardController;
use App\Http\Controllers\TemplateController;
use App\Http\Middleware\CheckUserRoleAndPermission;

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
    Route::get('/boards', [PlayerBoardController::class, 'index'])
        ->middleware([CheckUserRoleAndPermission::class . ':user']);

    // Display Board with details for user
    Route::get('/board/{board}', [PlayerBoardController::class, 'show'])
        ->middleware([CheckUserRoleAndPermission::class . ':player,master']);

    // Join a new Board
    Route::post('/boards/join', [PlayerBoardController::class, 'store'])
        ->middleware(CheckUserRoleAndPermission::class . ':user');

    // Leave a Board
    Route::delete('/board/{board}/leave', [PlayerBoardController::class,'destroy']);

    // -----------------
    // Board Controller
    // -----------------

    // Add a Board
    Route::post('/boards/add', [BoardController::class, 'store'])
        ->middleware([CheckUserRoleAndPermission::class . ':user']);

    // Update a Board
    Route::put('/board/{board}/update', [BoardController::class, 'update'])
        ->middleware([CheckUserRoleAndPermission::class . ':master']);

    // Delete a Board
    Route::delete('/board/{board}/delete', [BoardController::class, 'destroy'])
        ->middleware([CheckUserRoleAndPermission::class . ':master']);

    // -----------------
    // Template Controller
    // -----------------

    // View templates
    Route::get('/templates', [TemplateController::class, 'index']);

    // Add a template
    Route::post('/templates/add', [TemplateController::class, 'store'])
        ->middleware([CheckUserRoleAndPermission::class . ':user']);

    // Update a template

    // Delete a template
});
