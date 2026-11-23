<?php

use App\Application\Game\Queries\GetRoomView;
use App\Domain\Game\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;

it('reconoce al host incluso si la base de datos devuelve los IDs como texto', function () {
    // MySQL y PostgreSQL pueden devolver columnas enteras como cadenas.
    // Con SQLite esos valores llegan como int y una comparación estricta
    // funciona por casualidad: este test fija el comportamiento real.
    $game = new Game([
        'code' => 'HOST',
        'status' => GameStatus::Waiting,
        'version' => 0,
    ]);
    $game->id = 7;
    $game->host_player_id = '12';

    $player = new Player([
        'nickname' => 'Ana',
        'color' => 'yellow',
        'seat' => 0,
        'is_host' => true,
        'is_connected' => true,
    ]);
    $player->id = '12';
    $player->game_id = '7';
    $player->setRelation('game', $game);

    expect($player->isHostOf($game))->toBeTrue()
        ->and($game->host_player_id)->toBeInt()
        ->and($player->id)->toBeInt();

    $other = new Player([
        'nickname' => 'Beto',
        'color' => 'blue',
        'seat' => 1,
        'is_host' => false,
        'is_connected' => true,
    ]);
    $other->id = '13';
    $other->game_id = '7';

    $game->setRelation('players', collect([$player, $other]));

    $view = app(GetRoomView::class)->handle($game, $player)->toArray();

    expect($view['players'][0]['isHost'])->toBeTrue()
        ->and($view['canStart'])->toBeTrue();
});
