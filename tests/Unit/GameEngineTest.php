<?php

use App\Domain\Game\Services\BoardFactory;
use App\Domain\Game\Services\GameEngine;

function boardFor(int $arms): array
{
    $colors = [];
    for ($seat = 0; $seat < min($arms, 2); $seat++) {
        $colors[$seat] = $seat === 0 ? 'red' : 'blue';
    }

    return BoardFactory::forArms($arms, $colors);
}

function twoPlayerState(array $board): array
{
    return GameEngine::initialState([
        1 => ['id' => 1, 'nickname' => 'Ana', 'color' => 'red', 'seat' => 0],
        2 => ['id' => 2, 'nickname' => 'Beto', 'color' => 'blue', 'seat' => 1],
    ], 1);
}

function putPiece(array &$state, int $playerId, int $slot, ?int $progress): string
{
    $id = GameEngine::pieceId($playerId, $slot);
    $state['pieces'][$id]['progress'] = $progress;

    return $id;
}

it('saca una ficha de casa solo con un 5', function () {
    $board = boardFor(3);
    $state = twoPlayerState($board);
    $engine = new GameEngine($board);

    $rolled = $engine->roll(4, $state);

    expect($rolled['state']['legalMoves'])->toBe([])
        ->and($rolled['state']['currentPlayerId'])->toBe(2);

    $state = twoPlayerState($board);
    $rolled = $engine->roll(5, $state);

    expect($rolled['state']['legalMoves'])->toContain('1-0')
        ->and($rolled['state']['legalMoves'])->toHaveCount(4);

    $moved = $engine->move('1-0', $rolled['state']);

    expect($moved['state']['pieces']['1-0']['progress'])->toBe(0)
        ->and($moved['state']['currentPlayerId'])->toBe(2);
});

it('captura al caer sobre una ficha rival en casilla no segura', function () {
    $board = boardFor(3);
    $state = twoPlayerState($board);
    putPiece($state, 2, 0, 0); // seat 1, progress 0 -> celda 12 (segura)
    putPiece($state, 2, 1, 1); // seat 1, progress 1 -> celda 13
    putPiece($state, 1, 0, 13); // seat 0, progress 13 -> celda 13
    putPiece($state, 1, 1, 5); // ficha propia para dar legalidad extra

    // El host es seat 0; cambiamos el turno a seat 1 para mover hacia la 13.
    $state['currentPlayerId'] = 2;
    $engine = new GameEngine($board);

    $state['pieces']['2-0']['progress'] = 0;
    $rolled = $engine->roll(1, $state);
    $moved = $engine->move('2-0', $rolled['state']);

    expect($moved['state']['pieces']['1-0']['progress'])->toBeNull()
        ->and($moved['state']['captures']['2'])->toBe(1)
        ->and(collect($moved['events'])->pluck('type'))->toContain('capture');
});

it('no captura en casilla segura', function () {
    $board = boardFor(3);
    $state = twoPlayerState($board);
    putPiece($state, 2, 0, 5); // seat 1 -> celda 17
    putPiece($state, 1, 0, 16); // seat 0 -> celda 16
    putPiece($state, 1, 1, 18); // rival en destino 18 (segura)
    $state['currentPlayerId'] = 2;
    $engine = new GameEngine($board);

    $rolled = $engine->roll(1, $state);
    $moved = $engine->move('2-0', $rolled['state']);

    expect($moved['state']['pieces']['1-1']['progress'])->toBe(18)
        ->and($moved['state']['captures']['2'])->toBe(0)
        ->and(collect($moved['events'])->pluck('type'))->not->toContain('capture');
});

it('concede turno extra con un 6 usado en un movimiento legal', function () {
    $board = boardFor(3);
    $state = twoPlayerState($board);
    putPiece($state, 1, 0, 0);
    $engine = new GameEngine($board);

    $rolled = $engine->roll(6, $state);
    $moved = $engine->move('1-0', $rolled['state']);

    expect($moved['state']['currentPlayerId'])->toBe(1)
        ->and($moved['state']['consecutiveSixes'])->toBe(1)
        ->and($moved['state']['pendingRoll'])->toBeNull()
        ->and(collect($moved['events'])->pluck('type'))->toContain('extra_turn');
});

it('pierde el turno con el tercer 6 consecutivo', function () {
    $board = boardFor(3);
    $state = twoPlayerState($board);
    putPiece($state, 1, 0, 0);
    $engine = new GameEngine($board);

    foreach ([0, 6] as $progress) {
        $state['pieces']['1-0']['progress'] = $progress;
        $rolled = $engine->roll(6, $state);
        $state = $engine->move('1-0', $rolled['state'])['state'];
    }

    $state['pieces']['1-0']['progress'] = 12;
    $third = $engine->roll(6, $state);

    expect($third['state']['currentPlayerId'])->toBe(2)
        ->and($third['state']['consecutiveSixes'])->toBe(0)
        ->and($third['state']['pendingRoll'])->toBeNull()
        ->and(collect($third['events'])->pluck('type'))->toContain('third_six');
});

it('salta el turno de un jugador desconectado', function () {
    $board = boardFor(3);
    $state = twoPlayerState($board);
    $state['players'][1]['isConnected'] = false;
    putPiece($state, 1, 0, 0);
    $engine = new GameEngine($board);

    $rolled = $engine->roll(2, $state);

    expect($rolled['state']['currentPlayerId'])->toBe(2)
        ->and($rolled['state']['consecutiveSixes'])->toBe(0);
});

it('no permite pasarse de la meta', function () {
    $board = boardFor(3);
    $goal = $board['arms'] * $board['cellsPerArm'] + $board['homeLength'];
    $state = twoPlayerState($board);
    putPiece($state, 1, 0, $goal - 1);
    $engine = new GameEngine($board);

    $rolled = $engine->roll(2, $state);

    expect($rolled['state']['legalMoves'])->toBe([])
        ->and($rolled['state']['currentPlayerId'])->toBe(2);
});

it('termina la partida al meter la cuarta ficha en la meta', function () {
    $board = boardFor(3);
    $goal = $board['arms'] * $board['cellsPerArm'] + $board['homeLength'];
    $state = twoPlayerState($board);
    putPiece($state, 1, 0, $goal);
    putPiece($state, 1, 1, $goal);
    putPiece($state, 1, 2, $goal);
    putPiece($state, 1, 3, $goal - 1);
    putPiece($state, 2, 0, 4);
    $engine = new GameEngine($board);

    $rolled = $engine->roll(1, $state);
    $moved = $engine->move('1-3', $rolled['state']);

    expect($moved['state']['status'])->toBe('finished')
        ->and($moved['state']['winnerPlayerId'])->toBe(1)
        ->and($moved['state']['result']['ranking'][0]['playerId'])->toBe(1)
        ->and(collect($moved['events'])->pluck('type'))->toContain('game_finished');
});

it('genera tableros parametrizados de 3, 4 y 6 brazos con casas y pasillos', function () {
    foreach ([3, 4, 6] as $arms) {
        $board = boardFor($arms);
        $expectedCells = $arms * config("parchis.boards.{$arms}.cells_per_arm");

        expect($board['cells'])->toHaveCount($expectedCells)
            ->and($board['bases'])->toHaveCount($arms)
            ->and($board['homes'])->toHaveCount($arms)
            ->and($board['homes'][0]['cells'])->toHaveCount($arms === 4 ? 7 : 6)
            ->and($board['homes'][0]['goals'])->toHaveCount($arms === 4 ? 1 : 4)
            ->and(array_sum(array_map(fn (array $home): int => count($home['goals']), $board['homes'])))->toBe($arms === 4 ? 4 : $arms * 4)
            ->and($board['safeCells'])->toHaveCount($arms === 4 ? 6 : 4)
            ->and($board['cells'][0]['next'])->toBe(1);
    }
});
