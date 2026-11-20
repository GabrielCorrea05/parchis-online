<?php

namespace App\Application\Game\Queries;

use App\Application\Game\DTOs\GameSnapshot;
use App\Models\Game;
use App\Models\Player;

final class GetGameSnapshot
{
    public function __construct(private readonly GetGameResult $result) {}

    public function handle(Game $game, ?Player $current): GameSnapshot
    {
        $players = array_values($game->players->map(fn (Player $player): array => [
            'id' => $player->id,
            'nickname' => $player->nickname,
            'color' => $player->color,
            'seat' => $player->seat,
            'isHost' => $game->host_player_id === $player->id,
            'isYou' => $current?->id === $player->id,
            'isConnected' => $player->is_connected,
        ])->all());

        $state = $game->state ?? [];
        $version = (int) ($game->version ?? 0);
        $pendingRoll = $state['pendingRoll'] ?? null;
        $legalMoves = $state['legalMoves'] ?? [];

        return new GameSnapshot(
            gameId: (int) $game->id,
            code: $game->code,
            status: $game->status->value,
            arms: (int) ($game->arms ?? 0),
            board: $game->board_config ?? [],
            players: $players,
            youPlayerId: $current?->id,
            state: $state,
            version: $version,
            currentPlayerId: $state['currentPlayerId'] ?? null,
            pendingRoll: $pendingRoll === null ? null : (int) $pendingRoll,
            legalMoves: is_array($legalMoves) ? array_values($legalMoves) : [],
            result: $game->isFinished() ? $this->result->handle($game) : null,
        );
    }
}
