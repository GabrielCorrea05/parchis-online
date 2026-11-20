<?php

namespace App\Domain\Game\Services;

use App\Domain\Game\Exceptions\GameRuleViolation;

/**
 * Reglas de movimiento puras. No conoce Laravel, HTTP ni Reverb.
 */
final class MovementValidator
{
    private readonly int $ringCells;

    private readonly int $goal;

    /**
     * @param  array{arms: int, cellsPerArm: int, homeLength: int}  $board
     */
    public function __construct(private readonly array $board)
    {
        $this->ringCells = (int) $board['arms'] * (int) $board['cellsPerArm'];
        $this->goal = $this->ringCells + (int) $board['homeLength'];
    }

    public function goal(): int
    {
        return $this->goal;
    }

    public function ringCells(): int
    {
        return $this->ringCells;
    }

    /**
     * @param  array<string, array{id: string, playerId: int, seat: int, slot: int, progress: int|null}>  $pieces
     * @return list<string>
     */
    public function legalMoves(int $roll, array $pieces, int $playerId): array
    {
        $legal = [];

        foreach ($pieces as $piece) {
            if ($piece['playerId'] !== $playerId) {
                continue;
            }

            $progress = $piece['progress'];

            // Casa: solo se sale con un 5, a progress = 0.
            if ($progress === null) {
                if ($roll === 5) {
                    $legal[] = $piece['id'];
                }

                continue;
            }

            // Meta: las fichas terminadas ya no se mueven.
            if ($progress >= $this->goal) {
                continue;
            }

            // No se puede pasar de largo: la meta exige tirada exacta.
            if ($progress + $roll <= $this->goal) {
                $legal[] = $piece['id'];
            }
        }

        return $legal;
    }

    /**
     * @param  array<string, array{id: string, playerId: int, seat: int, slot: int, progress: int|null}>  $pieces
     */
    public function assertLegal(string $pieceId, int $roll, array $pieces, int $playerId): void
    {
        if (! isset($pieces[$pieceId])) {
            throw new GameRuleViolation('Esa ficha no existe.');
        }

        if ($pieces[$pieceId]['playerId'] !== $playerId) {
            throw new GameRuleViolation('Esa ficha no pertenece al jugador activo.');
        }

        if (! in_array($pieceId, $this->legalMoves($roll, $pieces, $playerId), true)) {
            throw new GameRuleViolation('Movimiento ilegal para la tirada actual.');
        }
    }

    /**
     * Índice del anillo que ocupa una ficha, o null si está en casa/columna/meta.
     */
    public function ringCellFor(int $seat, int $progress): ?int
    {
        if ($progress < 0 || $progress >= $this->ringCells) {
            return null;
        }

        return ($seat * (int) $this->board['cellsPerArm'] + $progress) % $this->ringCells;
    }
}
