<?php

use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user !== null && (string) $user->id === (string) $id;
});

/*
 | No hay login: la autorización del canal se resuelve con el token
 | guardado en la cookie de la sala (o la cabecera X-Player-Token).
 */
Broadcast::channel('room.{code}', function ($user, string $code): array|false {
    // ResolvePlayerToken ya ha resuelto este jugador desde X-Player-Token
    // (la cabecera gana sobre la cookie compartida entre pestañas).
    if (! $user instanceof Player) {
        return false;
    }

    $game = Game::where('code', strtoupper($code))->first();

    if ($game === null || (int) $user->game_id !== (int) $game->id) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->nickname,
        'color' => $user->color,
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
