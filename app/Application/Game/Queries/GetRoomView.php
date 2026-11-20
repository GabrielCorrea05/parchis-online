<?php

namespace App\Application\Game\Queries;

use App\Application\Game\DTOs\RoomView;
use App\Domain\Game\Services\BoardFactory;
use App\Models\Game;
use App\Models\Player;

final class GetRoomView
{
    public function handle(Game $game, ?Player $current): RoomView
    {
        $players = $game->players->map(fn (Player $player): array => [
            'id' => $player->id,
            'nickname' => $player->nickname,
            'color' => $player->color,
            'seat' => $player->seat,
            'isHost' => $game->host_player_id === $player->id,
            'isConnected' => $player->is_connected,
            'isYou' => $current?->id === $player->id,
        ])->all();

        $playerCount = count($players);
        $previewArms = $game->arms ?? BoardFactory::armsForPlayers(max(2, $playerCount));

        $colors = [];
        foreach ($game->players as $player) {
            $colors[$player->seat] = $player->color;
        }

        $board = $game->board_config ?? BoardFactory::forArms($previewArms, $colors);

        $canStart = $current !== null
            && $game->host_player_id === $current->id
            && $game->isWaiting()
            && $playerCount >= config('parchis.min_players')
            && $playerCount <= config('parchis.max_players');

        return new RoomView(
            code: $game->code,
            status: $game->status->value,
            arms: $game->arms,
            board: $board,
            players: $players,
            hostPlayerId: $game->host_player_id,
            playerCount: $playerCount,
            minPlayers: config('parchis.min_players'),
            maxPlayers: config('parchis.max_players'),
            currentPlayerId: $current?->id,
            canStart: $canStart,
        );
    }
}
