<?php

use App\Application\Game\Services\ResultBuilder;
use App\Domain\Game\Enums\GameStatus;
use App\Domain\Game\Services\BoardFactory;
use App\Domain\Game\Services\DiceRoller;
use App\Domain\Game\Services\GameEngine;
use App\Infrastructure\Broadcasting\GameFinished;
use App\Infrastructure\Dice\FakeDiceRoller;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Player;
use App\Support\PlayerSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function raToken(): string
{
    return 'ra-'.Str::random(32);
}

/**
 * @param  array<int, int>  $progress  progreso de las cuatro fichas de cada jugador
 * @return array{game: Game, players: array<int, Player>, tokens: array<int, string>, board: array<string, mixed>, state: array<string, mixed>}
 */
function raGame(array $progress, bool $finished = true, int $version = 3): array
{
    $colors = ['red', 'blue', 'yellow'];
    $colorsBySeat = [];
    for ($seat = 0; $seat < count($progress); $seat++) {
        $colorsBySeat[$seat] = $colors[$seat];
    }

    $board = BoardFactory::forArms(4, $colorsBySeat);
    $goal = $board['arms'] * $board['cellsPerArm'] + $board['homeLength'];

    $enginePlayers = [];
    foreach ($progress as $index => $values) {
        $enginePlayers[$index + 1] = [
            'id' => $index + 1,
            'nickname' => ['Ana', 'Beto', 'Carmen'][$index],
            'color' => $colors[$index],
            'seat' => $index,
            'isConnected' => true,
        ];
    }

    $state = GameEngine::initialState($enginePlayers, 1);

    foreach ($progress as $playerIndex => $values) {
        foreach ($values as $slot => $value) {
            $state['pieces'][GameEngine::pieceId($playerIndex + 1, $slot)]['progress'] = $value === -1 ? null : $value;
        }
    }

    if ($finished) {
        $state['status'] = 'finished';
        $state['winnerPlayerId'] = 1;
        $state['turns'] = 61;
        $state['totalRolls'] = 61;
        $state['captures'] = ['1' => 3, '2' => 1, '3' => 0];
    }

    $game = Game::create([
        'code' => 'RA'.Str::upper(Str::random(2)),
        'status' => $finished ? GameStatus::Finished : GameStatus::InProgress,
        'version' => $version,
        'arms' => 4,
        'board_config' => $board,
        'state' => $state,
        'started_at' => now()->subMinutes(15),
        'finished_at' => $finished ? now() : null,
        'winner_player_id' => $finished ? 1 : null,
    ]);

    $players = [];
    $tokens = [];

    foreach ($enginePlayers as $id => $data) {
        $tokens[$id] = raToken();
        $players[$id] = $game->players()->create([
            'nickname' => $data['nickname'],
            'color' => $data['color'],
            'token_hash' => hash('sha256', $tokens[$id]),
            'is_host' => $id === 1,
            'is_connected' => true,
            'seat' => $data['seat'],
        ]);
    }

    $game->update(['host_player_id' => $players[1]->id]);

    if ($finished) {
        raPersistResult($game->fresh(), $state);
    }

    return compact('game', 'players', 'tokens', 'board', 'state');
}

function raPersistResult(Game $game, array $state): array
{
    $payload = app(ResultBuilder::class)->build($game, $state);

    GameResult::create([
        'game_id' => $game->id,
        'winner_player_id' => $state['winnerPlayerId'] ?? null,
        'board_arms' => $game->arms,
        'duration_seconds' => $payload['duration_seconds'],
        'turns' => $payload['turns'],
        'total_rolls' => $payload['total_rolls'],
        'total_captures' => $payload['total_captures'],
        'ranking' => $payload['ranking'],
        'payload' => $payload,
        'finished_at' => $game->finished_at,
    ]);

    return $payload;
}

it('devuelve el resultado final con clasificación, capturas y alias en español', function () {
    $goal = 75;
    $data = raGame([
        [$goal, $goal, $goal, $goal],           // 4 en meta
        [$goal, $goal, 20, 15],                 // 2 en meta, progreso 35
        [$goal, $goal, 25, 25],                 // 2 en meta, progreso 50
    ]);

    $response = $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->getJson("/api/games/{$data['game']->code}/result")
        ->assertOk()
        ->json();

    expect($response['game_code'])->toBe($data['game']->code)
        ->and($response['status'])->toBe('finished')
        ->and($response['board_arms'])->toBe(4)
        ->and($response['winner'])->toMatchArray(['id' => 1, 'nickname' => 'Ana', 'color' => 'red'])
        ->and($response['ranking'])->toHaveCount(3)
        ->and($response['ranking'][0]['playerId'])->toBe(1)
        ->and($response['ranking'][0]['pieces_meta'])->toBe(4)
        ->and($response['ranking'][1]['playerId'])->toBe(3) // gana por progress_total
        ->and($response['ranking'][2]['playerId'])->toBe(2)
        ->and($response['ranking'][0]['captures'])->toBe(3)
        ->and($response['total_captures'])->toBe(4)
        ->and($response['turns'])->toBe(61)
        ->and($response['total_rolls'])->toBe(61)
        ->and((array) $response['captures_by_player'])->toBe(['1' => 3, '2' => 1, '3' => 0])
        ->and($response['duration_seconds'])->toBeGreaterThan(0);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][2])
        ->getJson("/api/partidas/{$data['game']->code}/resultado")
        ->assertOk()
        ->assertJsonPath('ranking.0.playerId', 1);
});

it('desempata por seat cuando coinciden fichas en meta y progreso', function () {
    $goal = 75;
    $data = raGame([
        [$goal, $goal, $goal, $goal],
        [$goal, $goal, 20, 20],
        [$goal, $goal, 20, 20],
    ]);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->getJson("/api/games/{$data['game']->code}/result")
        ->assertOk()
        ->assertJsonPath('ranking.1.playerId', 2)
        ->assertJsonPath('ranking.2.playerId', 3);
});

it('no revela ranking ni posiciones mientras la partida está en curso', function () {
    $data = raGame([[null, null, null, null], [null, null, null, null]], false);
    $expected = [
        'game_code' => $data['game']->code,
        'status' => 'en_curso',
        'board_arms' => 4,
        'winner' => null,
        'ranking' => [],
        'message' => 'La partida aún está en curso.',
    ];

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->getJson("/api/games/{$data['game']->code}/result")
        ->assertOk()
        ->assertExactJson($expected);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->getJson("/api/partidas/{$data['game']->code}/resultado")
        ->assertOk()
        ->assertExactJson($expected);
});

it('valida token y existencia de la partida en el resultado', function () {
    $data = raGame([[75, 75, 75, 75], [1, 2, 3, 4]]);
    $url = "/api/games/{$data['game']->code}/result";

    $this->getJson($url)->assertStatus(401);

    $other = raGame([[75, 75, 75, 75], [1, 2, 3, 4]]);
    $this->withHeader(PlayerSession::TOKEN_HEADER, $other['tokens'][1])
        ->getJson($url)
        ->assertStatus(403);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->getJson('/api/games/ZZZZ/result')
        ->assertNotFound();
});

it('guarda game_results y emite GameFinished cuando se gana', function () {
    Event::fake([GameFinished::class]);
    app()->bind(DiceRoller::class, fn () => new FakeDiceRoller([1]));

    $data = raGame([[75, 75, 75, 74], [1, 2, 3, 4]], false);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/roll")
        ->assertOk()
        ->assertJsonPath('pendingRoll', 1);

    $finished = $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/moves", ['pieceId' => '1-3'])
        ->assertOk()
        ->assertJsonPath('status', 'finished')
        ->assertJsonPath('currentPlayerId', 1)
        ->json();

    $game = $data['game']->refresh();

    expect($game->status)->toBe(GameStatus::Finished)
        ->and($game->winner_player_id)->toBe(1)
        ->and($game->finished_at)->not->toBeNull()
        ->and($game->result)->not->toBeNull()
        ->and($game->result->payload['winner']['id'])->toBe(1)
        ->and($game->events()->pluck('type'))->toContain('game_finished');

    Event::assertDispatched(GameFinished::class, fn (GameFinished $event) => $event->result['winner']['id'] === 1);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->getJson("/api/games/{$data['game']->code}/result")
        ->assertOk()
        ->assertJsonPath('status', 'finished')
        ->assertJsonPath('winner.id', 1);

    expect($finished['result']['winner']['id'])->toBe(1);
});

it('entrega el snapshot completo al recargar la partida', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    $data = raGame([[0, null, null, null], [null, null, null, null]], false);

    // Sin la cabecera X-Inertia, Inertia entrega la vista raíz con el page;
    // assertInertia trabaja sobre esa representación.
    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->get("/games/{$data['game']->code}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Board')
            ->where('snapshot.gameId', $data['game']->id)
            ->where('snapshot.code', $data['game']->code)
            ->where('snapshot.youPlayerId', 1)
            ->where('snapshot.currentPlayerId', 1)
            ->where('snapshot.version', 3)
            ->where('snapshot.board.arms', 4)
            ->where('snapshot.state.currentPlayerId', 1));

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->get("/games/{$data['game']->code}Unexpected")
        ->assertNotFound();
});

it('publica el historial publico y el detalle de la partida terminada', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    $data = raGame([[75, 75, 75, 75], [1, 2, 3, 4]]);

    $this->get('/partidas')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Results/Index')
            ->where('history.0.code', $data['game']->code)
            ->where('history.0.winner.nickname', 'Ana')
            ->where('history.0.playerCount', 2));

    $this->get("/partidas/{$data['game']->code}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Results/Show')
            ->where('result.status', 'finished')
            ->where('result.winner.nickname', 'Ana'));

    $this->get('/partidas/ZZZZ')->assertNotFound();
});
