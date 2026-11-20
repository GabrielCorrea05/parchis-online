<?php

use App\Models\Game;
use App\Models\Player;
use App\Support\PlayerSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user !== null && (string) $user->id === (string) $id;
});

/*
 | No hay login: la autorización del canal se resuelve con el token
 | guardado en la cookie de la sala (o la cabecera X-Player-Token).
 */
Broadcast::channel('room.{code}', function ($user, string $code) {
    $game = Game::where('code', strtoupper($code))->first();

    if ($game === null) {
        return false;
    }

    $player = PlayerSession::playerFor($game);

    if ($player === null) {
        return false;
    }

    return [
        'id' => $player->id,
        'name' => $player->nickname,
        'color' => $player->color,
    ];
});

/*
 | El canal privado de partida también se autoriza con X-Player-Token,
 | resuelto por ResolvePlayerToken a partir de private-game.{id}.
 */
Broadcast::channel('game.{gameId}', function ($user, string $gameId): array|false {
    if (! $user instanceof Player) {
        return false;
    }

    $game = Game::find((int) $gameId);

    if ($game === null || (int) $user->game_id !== (int) $game->id) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->nickname,
        'color' => $user->color,
    ];
});
