<?php

namespace Database\Seeders;

use App\Application\Game\Services\ResultBuilder;
use App\Domain\Game\Enums\GameStatus;
use App\Domain\Game\Services\BoardFactory;
use App\Domain\Game\Services\GameEngine;
use App\Models\Game;
use App\Models\GameResult;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Crea una partida terminada de 4 jugadores para probar el historial y el
 * endpoint de resultado sin tener que jugar una partida completa.
 */
final class DemoGameSeeder extends Seeder
{
    use WithoutModelEvents;

    public const CODE = 'DEMO';

    public const TOKEN_PREFIX = 'demo-token-';

    public function run(): void
    {
        if (Game::where('code', self::CODE)->exists()) {
            $this->command?->info('La partida demo DEMO ya existe; no se ha tocado nada.');

            return;
        }

        $game = Game::create([
            'code' => self::CODE,
            'status' => GameStatus::Waiting,
            'version' => 0,
        ]);

        $nicknames = ['Lucía', 'Marco', 'Ana', 'Iker'];
        $colors = ['red', 'blue', 'yellow', 'green'];
        $players = [];

        foreach ($nicknames as $seat => $nickname) {
            $players[] = $game->players()->create([
                'nickname' => $nickname,
                'color' => $colors[$seat],
                'token_hash' => hash('sha256', self::TOKEN_PREFIX.($seat + 1)),
                'is_host' => $seat === 0,
                'is_connected' => true,
                'seat' => $seat,
            ]);
        }

        $host = $players[0];
        $game->update(['host_player_id' => $host->id]);

        $colorsBySeat = [];
        $enginePlayers = [];

        foreach ($players as $player) {
            $seat = (int) $player->seat;
            $colorsBySeat[$seat] = (string) $player->color;
            $enginePlayers[(int) $player->id] = [
                'id' => (int) $player->id,
                'nickname' => (string) $player->nickname,
                'color' => (string) $player->color,
                'seat' => $seat,
                'isConnected' => true,
            ];
        }

        $board = BoardFactory::forArms(4, $colorsBySeat);
        $state = GameEngine::initialState($enginePlayers, (int) $host->id);
        $goal = $board['arms'] * $board['cellsPerArm'] + $board['homeLength'];

        $progress = [
            [$goal, $goal, $goal, $goal],
            [$goal, $goal, 32, 20],
            [$goal, 40, 35, 18],
            [50, 42, 30, 12],
        ];

        foreach ($players as $index => $player) {
            foreach ($progress[$index] as $slot => $value) {
                $state['pieces'][GameEngine::pieceId((int) $player->id, $slot)]['progress'] = $value;
            }
        }

        $state['status'] = 'finished';
        $state['winnerPlayerId'] = (int) $host->id;
        $state['turns'] = 61;
        $state['totalRolls'] = 61;
        $state['captures'] = [
            (string) $players[0]->id => 3,
            (string) $players[1]->id => 1,
            (string) $players[2]->id => 0,
            (string) $players[3]->id => 0,
        ];

        $startedAt = now()->subMinutes(15);
        $finishedAt = now();

        $game->update([
            'status' => GameStatus::Finished,
            'arms' => 4,
            'board_config' => $board,
            'state' => $state,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'winner_player_id' => (int) $host->id,
            'version' => 12,
        ]);

        foreach ([
            ['game_started', ['arms' => 4, 'players' => array_values($enginePlayers)]],
            ['dice_rolled', ['playerId' => (int) $host->id, 'value' => 5, 'turn' => 61]],
            ['game_finished', ['winnerPlayerId' => (int) $host->id, 'version' => 12]],
        ] as $sequence => [$type, $payload]) {
            $game->events()->create([
                'sequence' => $sequence + 1,
                'type' => $type,
                'payload' => $payload,
            ]);
        }

        $fresh = $game->fresh();
        $result = app(ResultBuilder::class)->build($fresh, $state);

        GameResult::create([
            'game_id' => $fresh->id,
            'winner_player_id' => (int) $host->id,
            'board_arms' => 4,
            'duration_seconds' => $result['duration_seconds'],
            'turns' => $result['turns'],
            'total_rolls' => $result['total_rolls'],
            'total_captures' => $result['total_captures'],
            'ranking' => $result['ranking'],
            'payload' => $result,
            'finished_at' => $finishedAt,
        ]);

        $this->command?->info('Partida demo creada: code=DEMO, token=demo-token-1 (demo-token-2..4 para el resto).');
    }
}
