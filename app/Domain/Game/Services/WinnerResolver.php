<?php

namespace App\Domain\Game\Services;

/**
 * Clasificación cerrada en AGENTS.md:
 * 1) más fichas en meta, 2) mayor progreso total, 3) menor seat.
 */
final class WinnerResolver
{
    public function __construct(private readonly int $goal) {}

    /**
     * @param  array<string, array{id: string, playerId: int, seat: int, slot: int, progress: int|null}>  $pieces
     * @param  array<int, array{id: int, nickname: string, color: string, seat: int}>  $players
     * @return list<array{playerId: int, nickname: string, color: string, seat: int, pieces_meta: int, progress_total: int}>
     */
    public function rank(array $pieces, array $players): array
    {
        $stats = [];

        foreach ($players as $player) {
            $stats[$player['id']] = [
                'playerId' => $player['id'],
                'nickname' => $player['nickname'],
                'color' => $player['color'],
                'seat' => $player['seat'],
                'pieces_meta' => 0,
                'progress_total' => 0,
            ];
        }

        foreach ($pieces as $piece) {
            if (! isset($stats[$piece['playerId']])) {
                continue;
            }

            $progress = $piece['progress'] ?? 0;
            $stats[$piece['playerId']]['progress_total'] += $progress;

            if ($piece['progress'] !== null && $piece['progress'] >= $this->goal) {
                $stats[$piece['playerId']]['pieces_meta']++;
            }
        }

        $ranking = array_values($stats);

        usort($ranking, function (array $a, array $b): int {
            return ($b['pieces_meta'] <=> $a['pieces_meta'])
                ?: ($b['progress_total'] <=> $a['progress_total'])
                ?: ($a['seat'] <=> $b['seat']);
        });

        return $ranking;
    }
}
