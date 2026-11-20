<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/games/{code}', [GameController::class, 'show'])->name('api.games.show');
Route::post('/games/{code}/roll', [GameController::class, 'roll'])->middleware('throttle:game-action')->name('api.games.roll');
Route::post('/games/{code}/moves', [GameController::class, 'move'])->middleware('throttle:game-action')->name('api.games.move');
Route::get('/games/{code}/result', [GameController::class, 'result'])->name('api.games.result');
Route::post('/games/{code}/rematch', [GameController::class, 'rematch'])->middleware('throttle:game-action')->name('api.games.rematch');
Route::post('/games/{code}/rematch/token', [GameController::class, 'rematchToken'])->middleware('throttle:game-action')->name('api.games.rematch-token');

Route::get('/partidas/{code}/resultado', [GameController::class, 'result'])->name('api.resultados.show');
