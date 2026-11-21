<?php

namespace App\Application\Game\Services;

use App\Application\Game\Exceptions\GameActionRejected;

/**
 * Valida la configuración persistida antes de entregarla al motor de dominio.
 */
final class GameBoard
{
    /**
     * @return array{arms: int, cellsPerArm: int, homeLength: int, safeCells?: list<int>, startIndices?: list<int>}
     */
    public static function forEngine(mixed $board): array
    {
        if (! is_array($board)
            || ! isset($board['arms'], $board['cellsPerArm'], $board['homeLength'])
            || ! is_int($board['arms'])
            || ! is_int($board['cellsPerArm'])
            || ! is_int($board['homeLength'])) {
            throw new GameActionRejected('La configuración del tablero no está disponible.');
        }

        $safeCells = $board['safeCells'] ?? null;

        if ($safeCells !== null && ! is_array($safeCells)) {
            throw new GameActionRejected('La configuración del tablero no es válida.');
        }

        $startIndices = $board['startIndices'] ?? null;

        if ($startIndices !== null && ! is_array($startIndices)) {
            throw new GameActionRejected('La configuración del tablero no es válida.');
        }

        $shape = [
            'arms' => $board['arms'],
            'cellsPerArm' => $board['cellsPerArm'],
            'homeLength' => $board['homeLength'],
        ];

        if ($safeCells !== null) {
            $shape['safeCells'] = array_values(array_map('intval', $safeCells));
        }

        if ($startIndices !== null) {
            $shape['startIndices'] = array_values(array_map('intval', $startIndices));
        }

        return $shape;
    }
}
