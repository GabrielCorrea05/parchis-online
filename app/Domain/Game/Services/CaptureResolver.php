<?php

namespace App\Domain\Game\Services;

/**
 * Resuelve capturas al caer, nunca al pasar por encima.
 */
final class CaptureResolver
{
    /** @var list<int> */
    private readonly array $safeCells;

    private readonly MovementValidator $movement;

    /**
     * @param  array{arms: int, cellsPerArm: int, homeLength: int, safeCells?: list<int>}  $board
     */
    public function __construct(private readonly array $board)
    {
        $this->safeCells = array_map('intval', $board['safeCells'] ?? []);
        $this->movement = new MovementValidator($board);
    }

    /**
     * @param  array<string, array{id: string, playerId: int, seat: int, slot: int, progress: int|null}>  $pieces
     * @return list<array{pieceId: string, playerId: int, cell: int}>
     */
    public function resolve(
        array &$pieces,
        int $moverPlayerId,
        int $moverSeat,
        int $destinationProgress,
        ?int $fromProgress,
    ): array {
        $cell = $this->movement->ringCellFor($moverSeat, $destinationProgress);

        // Solo se captura al caer en el anillo. Salir de casa nunca captura.
        if ($cell === null || $fromProgress === null) {
            return [];
        }

        if (in_array($cell, $this->safeCells, true)) {
            return [];
        }

        $captured = [];

        foreach ($pieces as $id => &$piece) {
            if ($piece['playerId'] === $moverPlayerId || $piece['progress'] === null) {
                continue;
            }

            $rivalCell = $this->movement->ringCellFor(
                $piece['seat'],
                $piece['progress'],
            );

            if ($rivalCell !== $cell) {
                continue;
            }

            $piece['progress'] = null; // Vuelve a su hueco original (slot se conserva).
            $captured[] = [
                'pieceId' => $id,
                'playerId' => $piece['playerId'],
                'cell' => $cell,
            ];
        }
        unset($piece);

        return $captured;
    }
}
