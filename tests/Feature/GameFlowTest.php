<?php

use App\Domain\Game\Enums\GameStatus;
use App\Domain\Game\Services\BoardFactory;
use App\Domain\Game\Services\DiceRoller;
use App\Domain\Game\Services\GameEngine;
use App\Http\Middleware\ResolvePlayerToken;
use App\Infrastructure\Broadcasting\DiceRolled;
use App\Infrastructure\Broadcasting\GameStateUpdated;
use App\Infrastructure\Broadcasting\PieceMoved;
use App\Infrastructure\Broadcasting\RematchCreated;
use App\Infrastructure\Dice\FakeDiceRoller;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\GameResult;
use App\Models\Player;
use App\Support\PlayerSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function gfToken(): string
{
    return 'gf-'.Str::random(32);
}

/**
 * @param  list<int>  $dice
 * @return array{game: Game, players: array<int, Player>, tokens: array<int, string>, board: array<string, mixed>}
 */
function gfStartedGame(array $dice = [], int $version = 5): array
{
    if ($dice !== []) {
        app()->bind(DiceRoller::class, fn () => new FakeDiceRoller($dice));
    }

    $colors = ['red', 'blue'];
    $board = BoardFactory::forArms(3, [0 => 'red', 1 => 'blue']);

    $enginePlayers = [
        1 => ['id' => 1, 'nickname' => 'Ana', 'color' => 'red', 'seat' => 0, 'isConnected' => true],
        2 => ['id' => 2, 'nickname' => 'Beto', 'color' => 'blue', 'seat' => 1, 'isConnected' => true],
    ];
    $state = GameEngine::initialState($enginePlayers, 1);

    $game = Game::create([
        'code' => 'GF'.Str::upper(Str::random(2)),
        'status' => GameStatus::InProgress,
        'version' => $version,
        'arms' => 3,
        'board_config' => $board,
        'state' => $state,
        'started_at' => now()->subMinutes(5),
    ]);

    $players = [];
    $tokens = [];

    foreach ($colors as $seat => $color) {
        $tokens[$seat + 1] = gfToken();
        $players[$seat + 1] = $game->players()->create([
            'nickname' => $enginePlayers[$seat + 1]['nickname'],
            'color' => $color,
            'token_hash' => hash('sha256', $tokens[$seat + 1]),
            'is_host' => $seat === 0,
            'is_connected' => true,
            'seat' => $seat,
        ]);
    }

    $game->update(['host_player_id' => $players[1]->id]);

    return ['game' => $game, 'players' => $players, 'tokens' => $tokens, 'board' => $board];
}

function gfSetProgress(Game $game, string $pieceId, ?int $progress): void
{
    $state = $game->state;
    $state['pieces'][$pieceId]['progress'] = $progress;
    $game->update(['state' => $state]);
}

it('rechaza tirar fuera de turno, sin token y con token ajeno', function () {
    $host = gfStartedGame();
    $other = gfStartedGame();

    $this->withHeader(PlayerSession::TOKEN_HEADER, $host['tokens'][2])
        ->postJson("/api/games/{$host['game']->code}/roll")
        ->assertStatus(422)
        ->assertJsonPath('message', 'No es tu turno.');

    $this->flushHeaders()->postJson("/api/games/{$host['game']->code}/roll")
        ->assertStatus(401);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $other['tokens'][1])
        ->postJson("/api/games/{$host['game']->code}/roll")
        ->assertStatus(403);
});

it('rechaza mover una ficha ajena o sin tirada pendiente', function () {
    $data = gfStartedGame([5]);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/moves", ['pieceId' => '1-0'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'No hay ninguna tirada pendiente de mover.');

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/roll")
        ->assertOk();

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/moves", ['pieceId' => '2-0'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Esa ficha no pertenece al jugador activo.');
});

it('rechaza una versión obsoleta con 409', function () {
    $data = gfStartedGame([5], 9);

    $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/roll", ['version' => 8])
        ->assertStatus(409)
        ->assertJsonPath('message', 'La partida ha cambiado. Recarga el tablero para continuar.');
});

it('persiste el snapshot, incrementa la versión, escribe eventos y emite el contrato', function () {
    Event::fake([DiceRolled::class, PieceMoved::class, GameStateUpdated::class]);

    $data = gfStartedGame([5], 5);

    $rolled = $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/roll")
        ->assertOk()
        ->assertJsonPath('version', 6)
        ->assertJsonPath('pendingRoll', 5)
        ->assertJsonPath('currentPlayerId', 1)
        ->json();

    expect($rolled['legalMoves'])->toContain('1-0')
        ->and($rolled['state']['totalRolls'])->toBe(1);

    $moved = $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/moves", ['pieceId' => '1-0', 'version' => 6])
        ->assertOk()
        ->assertJsonPath('version', 7)
        ->assertJsonPath('pendingRoll', null)
        ->json();

    expect($moved['state']['pieces']['1-0']['progress'])->toBe(0)
        ->and($moved['state']['currentPlayerId'])->toBe(2)
        ->and($moved['players'][0]['isYou'])->toBeTrue();

    $game = $data['game']->refresh();

    expect($game->version)->toBe(7)
        ->and($game->state['pieces']['1-0']['progress'])->toBe(0);

    $types = $game->events()->pluck('type')->all();

    expect($types)->toContain('dice_rolled')
        ->and($types)->toContain('piece_out')
        ->and($types)->toContain('turn_advanced')
        ->and(GameEvent::where('game_id', $game->id)->distinct()->count('sequence'))->toBe(count($types));

    Event::assertDispatched(DiceRolled::class, fn (DiceRolled $event) => $event->value === 5 && $event->playerId === 1);
    Event::assertDispatched(PieceMoved::class, fn (PieceMoved $event) => $event->pieceId === '1-0' && $event->from === null && $event->to === 0);
    Event::assertDispatched(GameStateUpdated::class);
});

it('concede turno extra con el seis y pasa el turno con el tercer seis', function () {
    Event::fake([GameStateUpdated::class]);

    $data = gfStartedGame([6, 6, 6]);
    gfSetProgress($data['game'], '1-0', 0);

    for ($roll = 1; $roll <= 2; $roll++) {
        $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
            ->postJson("/api/games/{$data['game']->code}/roll")
            ->assertOk()
            ->assertJsonPath('pendingRoll', 6);

        $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
            ->postJson("/api/games/{$data['game']->code}/moves", ['pieceId' => '1-0'])
            ->assertOk()
            ->assertJsonPath('currentPlayerId', 1)
            ->assertJsonPath('state.consecutiveSixes', $roll);
    }

    $third = $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/roll")
        ->assertOk()
        ->assertJsonPath('currentPlayerId', 2)
        ->assertJsonPath('state.consecutiveSixes', 0)
        ->assertJsonPath('pendingRoll', null)
        ->json();

    expect($third['legalMoves'])->toBe([])
        ->and($data['game']->refresh()->events()->pluck('type'))->toContain('third_six');

    Event::assertDispatched(GameStateUpdated::class, fn (GameStateUpdated $event) => $event->reason === 'third_six');
});

it('pasa el turno aunque salga un seis sin movimientos legales', function () {
    Event::fake([GameStateUpdated::class]);

    $data = gfStartedGame([6]);

    $response = $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$data['game']->code}/roll")
        ->assertOk()
        ->assertJsonPath('currentPlayerId', 2)
        ->assertJsonPath('pendingRoll', null)
        ->json();

    expect($response['legalMoves'])->toBe([])
        ->and($data['game']->refresh()->events()->pluck('type'))
        ->toContain('no_legal_move');

    Event::assertDispatched(GameStateUpdated::class, fn (GameStateUpdated $event) => $event->reason === 'no_legal_move');
});

it('autoriza el canal privado de partida con el token de jugador', function () {
    $data = gfStartedGame();
    $authorize = Broadcast::getChannels()->get('game.{gameId}');

    expect($authorize)->not->toBeNull()
        ->and($authorize($data['players'][1], (string) $data['game']->id))->toBeArray();

    $foreigner = gfStartedGame();

    expect($authorize($foreigner['players'][1], (string) $data['game']->id))->toBeFalse()
        ->and($authorize(null, (string) $data['game']->id))->toBeFalse();

    $resolveUser = function (string $token, int $gameId): mixed {
        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-game.'.$gameId,
        ], [], [], ['HTTP_X_PLAYER_TOKEN' => $token]);
        $user = null;

        (new ResolvePlayerToken)->handle($request, function (Request $request) use (&$user) {
            $user = $request->user();

            return response()->noContent();
        });

        return $user;
    };

    expect($resolveUser($data['tokens'][1], (int) $data['game']->id)?->id)->toBe($data['players'][1]->id)
        ->and($resolveUser($foreigner['tokens'][1], (int) $data['game']->id))->toBeNull();
});

it('clona la sala en la revancha y devuelve el mismo token a cada jugador', function () {
    Event::fake([RematchCreated::class]);
    Event::fakeFor(function () use (&$data): void {
        $data = gfStartedGame();
    });

    $game = $data['game'];
    $state = $game->state;
    $board = $game->board_config;
    $goal = $board['arms'] * $board['cellsPerArm'] + $board['homeLength'];

    foreach ([0, 1, 2, 3] as $slot) {
        $state['pieces']["1-{$slot}"]['progress'] = $goal;
    }
    $state['status'] = 'finished';
    $state['winnerPlayerId'] = 1;

    $result = (new GameEngine($board))->ranking($state);
    $state['result'] = ['winnerPlayerId' => 1, 'ranking' => $result];

    $game->update([
        'state' => $state,
        'status' => GameStatus::Finished,
        'winner_player_id' => 1,
        'finished_at' => now(),
    ]);
    GameResult::create([
        'game_id' => $game->id,
        'winner_player_id' => 1,
        'board_arms' => 3,
        'duration_seconds' => 300,
        'turns' => 20,
        'total_rolls' => 20,
        'total_captures' => 0,
        'ranking' => $result,
        'payload' => ['game_code' => $game->code, 'status' => 'finished'],
        'finished_at' => now(),
    ]);

    $response = $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][1])
        ->postJson("/api/games/{$game->code}/rematch")
        ->assertOk()
        ->assertJsonPath('message', 'Nueva sala creada.')
        ->json();

    $newCode = $response['newCode'];
    $newGame = Game::where('code', $newCode)->firstOrFail();

    expect($newGame->status)->toBe(GameStatus::Waiting)
        ->and($newGame->host_player_id)->not->toBeNull()
        ->and($newGame->players()->count())->toBe(2);

    foreach ($data['players'] as $oldPlayer) {
        $clone = $newGame->players()->where('token_hash', $oldPlayer->token_hash)->firstOrFail();

        expect($clone->nickname)->toBe($oldPlayer->nickname)
            ->and($clone->color)->toBe($oldPlayer->color)
            ->and($clone->seat)->toBe($oldPlayer->seat)
            ->and($clone->is_host)->toBe($oldPlayer->is_host);

        $this->withHeader(PlayerSession::TOKEN_HEADER, $data['tokens'][$oldPlayer->seat + 1])
            ->postJson("/api/games/{$game->code}/rematch/token")
            ->assertOk()
            ->assertJsonPath('code', $newCode)
            ->assertJsonPath('playerToken', $data['tokens'][$oldPlayer->seat + 1]);
    }

    Event::assertDispatched(RematchCreated::class, fn (RematchCreated $event) => $event->newCode === $newCode);
});
