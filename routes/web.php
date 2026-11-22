<?php

use App\Http\Controllers\BoardPreviewController;
use App\Http\Controllers\GameViewController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RoomController::class, 'create'])->name('home');

Route::post('/rooms', [RoomController::class, 'store'])->middleware('throttle:room-create')->name('rooms.store');
Route::get('/rooms/{code}', [RoomController::class, 'show'])->name('rooms.show');
Route::post('/rooms/{code}/join', [RoomController::class, 'join'])->middleware('throttle:room-join')->name('rooms.join');
Route::post('/rooms/{code}/leave', [RoomController::class, 'leave'])->middleware('throttle:game-action')->name('rooms.leave');
Route::post('/rooms/{code}/start', [RoomController::class, 'start'])->middleware('throttle:game-action')->name('rooms.start');

Route::get('/games/{code}', [GameViewController::class, 'show'])->name('games.show');

Route::get('/board-preview', [BoardPreviewController::class, 'show'])->name('board.preview');

Route::get('/partidas', [ResultController::class, 'index'])->name('results.index');
Route::get('/partidas/{code}', [ResultController::class, 'show'])->name('results.show');
