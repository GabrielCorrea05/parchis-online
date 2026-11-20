<?php

namespace App\Application\Game\Services;

use App\Domain\Game\Services\GameEngine;
use App\Models\Game;
use App\Models\Player;

/**
 * Construye el resultado público de una partida terminada a partir del snapshot.
 * La clasificación la calcula siempre el motor de dominio.
 */
final class ResultBuilder
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function build(Game $game, array $state): array
    {
        $board = GameBoard::forEngine($game->board_config);
        $players = $state['players'] ?? [];

        $engine = new GameEngine($board);
        $ranking = $engine->ranking($state);

        $winnerId = (int) ($state['winnerPlayerId'] ?? $game->winner_player_id ?? 0);
        $winnerData = $players[$winnerId] ?? null;

        if ($winnerData === null) {
            $winner = Player::find($winnerId);
            $winnerData = $winner === null
                ? ['id' => $winnerId, 'nickname' => '—', 'color' => '']
                : ['id' => $winner->id, 'nickname' => $winner->nickname, 'color' => $winner->color];
        }

        $startedAt = $game->started_at;
        $finishedAt = $game->finished_at;
        $duration = $startedAt !== null && $finishedAt !== null
            ? abs($finishedAt->diffInSeconds($startedAt))
            : 0;

        $totalCaptures = 0;
        foreach ($state['captures'] ?? [] as $count) {
            $totalCaptures += (int) $count;
        }

        return [
            'game_code' => $game->code,
            'status' => 'finished',
            'board_arms' => (int) ($game->arms ?? $board['arms']),
            'winner' => [
                'id' => (int) ($winnerData['id'] ?? $winnerId),
                'nickname' => (string) ($winnerData['nickname'] ?? '—'),
                'color' => (string) ($winnerData['color'] ?? ''),
            ],
            'ranking' => $ranking,
            'started_at' => $startedAt?->toJSON(),
            'finished_at' => $finishedAt?->toJSON(),
            'duration_seconds' => $duration,
            'turns' => (int) ($state['turns'] ?? 0),
            'total_rolls' => (int) ($state['totalRolls'] ?? 0),
            'total_captures' => $totalCaptures,
            'captures_by_player' => (object) ($state['captures'] ?? []),
        ];
    }
}
