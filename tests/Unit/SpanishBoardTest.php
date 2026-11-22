<?php

use App\Domain\Game\Services\BoardFactory;
use App\Domain\Game\Services\MovementValidator;

it('dibuja el parchís español clásico con 68 casillas, 12 seguras y salidas 5/22/39/56', function () {
    $board = BoardFactory::forArms(4, [
        0 => 'red',
        1 => 'yellow',
        2 => 'blue',
        3 => 'green',
    ]);

    $label = fn (int $index): int => $board['cells'][$index]['label'];

    expect($board['cells'])->toHaveCount(68)
        ->and($board['cellsPerArm'])->toBe(17)
        ->and($board['homeLength'])->toBe(7)
        ->and($board['startIndices'])->toBe([0, 17, 34, 51])
        ->and(array_map($label, $board['startIndices']))->toBe([5, 22, 39, 56])
        ->and($board['safeCells'])->toHaveCount(12)
        ->and(array_map($label, $board['safeCells']))
        ->toBe([5, 12, 17, 22, 29, 34, 39, 46, 51, 56, 63, 68]);
});

it('comprueba la vuelta completa, la entrada al pasillo y la tirada exacta a meta', function () {
    $board = BoardFactory::forArms(4, [0 => 'red']);
    $validator = new MovementValidator([
        'arms' => $board['arms'],
        'cellsPerArm' => $board['cellsPerArm'],
        'homeLength' => $board['homeLength'],
    ]);

    expect($validator->ringCells())->toBe(68)
        ->and($validator->goal())->toBe(75)
        ->and($validator->ringCellFor(0, 0))->toBe(0)
        ->and($validator->ringCellFor(0, 67))->toBe(67)
        ->and($validator->ringCellFor(0, 68))->toBeNull();

    $pieces = [
        'p1' => [
            'id' => 'p1',
            'playerId' => 1,
            'seat' => 0,
            'slot' => 1,
            'progress' => 68,
        ],
    ];

    expect($validator->legalMoves(6, $pieces, 1))->toBe(['p1']);

    $pieces['p1']['progress'] = 74;

    expect($validator->legalMoves(1, $pieces, 1))->toBe(['p1'])
        ->and($validator->legalMoves(2, $pieces, 1))->toBe([]);
});

it('sitúa la salida, la casilla 68 y el pasillo en la rejilla 19x19', function () {
    $board = BoardFactory::forArms(4, [0 => 'red', 1 => 'blue']);
    $step = 800 / 19;

    $cell = fn (int $index): array => $board['cells'][$index];
    $point = fn (array $item): array => [$item['x'], $item['y']];
    $near = fn (array $a, array $b): bool => abs($a[0] - $b[0]) < 1 && abs($a[1] - $b[1]) < 1;

    // La casilla 5 (índice 0) está en (fila 14, columna 10); la 68 en (18, 9).
    expect($near($point($cell(0)), [400 + $step, 400 + 5 * $step]))->toBeTrue()
        ->and($near($point($cell(63)), [400, 400 + 9 * $step]))->toBeTrue()
        ->and($near($point($cell(67)), [400 + $step, 400 + 6 * $step]))->toBeTrue();

    // La casilla 68 y la primera del pasillo son contiguas en la columna 9.
    $last = $point($cell(63));
    $entrance = $point($board['homes'][0]['cells'][0]);

    expect(abs($last[1] - $entrance[1] - $step) < 1)->toBeTrue()
        ->and($board['homes'][0]['cells'])->toHaveCount(7)
        ->and($near($point(end($board['homes'][0]['cells'])), [400, 400 + 2 * $step]))->toBeTrue();

    // Una sola plantilla rotada: las salidas son las rotaciones de la primera.
    foreach ([1, 2, 3] as $arm) {
        $expected = match ($arm) {
            1 => [400 + 5 * $step, 400 - $step],
            2 => [400 - $step, 400 - 5 * $step],
            3 => [400 - 5 * $step, 400 + $step],
        };

        expect($near($point($cell($board['startIndices'][$arm])), $expected))->toBeTrue();
    }
});

it('mantiene las 12 seguras y el color de cada salida con brazos vacíos', function () {
    // El rojo fija el brazo 2 (salida 39); los demás brazos quedan vacíos.
    $board = BoardFactory::forArms(4, [2 => 'red']);

    expect($board['safeCells'])->toBe([0, 7, 12, 17, 24, 29, 34, 41, 46, 51, 58, 63])
        ->and(array_column($board['armsDetail'], 'color'))
        ->toBe(['yellow', 'blue', 'red', 'green'])
        ->and(array_column($board['armsDetail'], 'occupied'))
        ->toBe([false, false, true, false])
        ->and(array_filter($board['cells'], fn (array $cell): bool => ! isset($cell['label'])))
        ->toHaveCount(0)
        ->and(array_map(fn (array $cell): int => $cell['label'], $board['cells']))
        ->toBe([...range(5, 68), 1, 2, 3, 4]);
});
