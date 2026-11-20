<?php

use App\Domain\Game\Enums\Color;
use App\Domain\Game\Enums\GameStatus;
use App\Domain\Game\Services\BoardFactory;
use App\Infrastructure\Broadcasting\GameStarted;
use App\Infrastructure\Broadcasting\HostChanged;
use App\Infrastructure\Broadcasting\PlayerJoined;
use App\Infrastructure\Broadcasting\PlayerLeft;
use App\Models\Game;
use App\Models\Player;
use App\Support\PlayerSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function newToken(): string
{
    return 'token-'.Str::random(32);
}

function makeGame(string $code = 'ABCD', GameStatus $status = GameStatus::Waiting): Game
{
    return Game::create(['code' => $code, 'status' => $status, 'version' => 0]);
}

function addPlayer(Game $game, string $nickname, string $color, string $token, bool $host = false): Player
{
    $maxSeat = $game->players()->max('seat');

    $player = $game->players()->create([
        'nickname' => $nickname,
        'color' => $color,
        'token_hash' => hash('sha256', $token),
        'is_host' => $host,
        'is_connected' => true,
        'seat' => $maxSeat === null ? 0 : $maxSeat + 1,
    ]);

    if ($host) {
        $game->update(['host_player_id' => $player->id]);
    }

    return $player;
}

function tokenFromResponse($response, string $code): string
{
    foreach ($response->baseResponse->headers->getCookies() as $cookie) {
        if ($cookie->getName() === PlayerSession::COOKIE_NAME) {
            $tokens = json_decode((string) $cookie->getValue(), true);

            return $tokens[$code] ?? '';
        }
    }

    throw new RuntimeException('No se encontró la cookie del token.');
}

it('crea una sala y convierte al creador en host', function () {
    Event::fake([PlayerJoined::class]);

    $response = $this->post('/rooms', ['nickname' => 'Ana', 'color' => 'red']);

    $game = Game::firstOrFail();
    $host = $game->players()->firstOrFail();
    $token = tokenFromResponse($response, $game->code);

    expect($game->code)->toHaveLength(4)
        ->and($game->status)->toBe(GameStatus::Waiting)
        ->and($game->players()->count())->toBe(1)
        ->and($host->is_host)->toBeTrue()
        ->and($game->host_player_id)->toBe($host->id)
        ->and($token)->not->toBeEmpty()
        ->and($host->token_hash)->toBe(hash('sha256', $token));

    $response->assertRedirect('/rooms/'.$game->code);
    Event::assertDispatched(PlayerJoined::class);
});

it('permite unirse a una sala existente que aún no ha empezado', function () {
    Event::fake([PlayerJoined::class]);

    $game = makeGame();
    addPlayer($game, 'Ana', 'red', newToken(), host: true);
    $token = newToken();

    $this->post("/rooms/{$game->code}/join", ['nickname' => 'Beto', 'color' => 'blue'])
        ->assertRedirect('/rooms/'.$game->code);

    expect($game->players()->count())->toBe(2)
        ->and($game->players()->where('color', 'blue')->exists())->toBeTrue();

    Event::assertDispatched(PlayerJoined::class);
});

it('rechaza unirse cuando la sala está llena', function () {
    $game = makeGame();
    $hostToken = newToken();
    addPlayer($game, 'Ana', 'red', $hostToken, host: true);

    foreach (array_slice(Color::values(), 1) as $index => $color) {
        addPlayer($game, "Jugador{$index}", $color, newToken());
    }

    expect($game->players()->count())->toBe(6);

    $this->post("/rooms/{$game->code}/join", ['nickname' => 'Sexto', 'color' => 'red'])
        ->assertSessionHasErrors('code');

    expect($game->players()->count())->toBe(6);
});

it('rechaza unirse cuando la partida ya ha empezado', function () {
    $game = makeGame(status: GameStatus::InProgress);
    addPlayer($game, 'Ana', 'red', newToken(), host: true);

    $this->post("/rooms/{$game->code}/join", ['nickname' => 'Beto', 'color' => 'blue'])
        ->assertSessionHasErrors('code');

    expect($game->players()->count())->toBe(1);
});

it('rechaza que el host inicie con menos de dos jugadores', function () {
    $game = makeGame();
    $token = newToken();
    addPlayer($game, 'Ana', 'red', $token, host: true);
    $this->withUnencryptedCookie(PlayerSession::COOKIE_NAME, json_encode([$game->code => $token], JSON_THROW_ON_ERROR));

    $this->post("/rooms/{$game->code}/start")->assertSessionHasErrors('start');

    expect($game->refresh()->status)->toBe(GameStatus::Waiting)
        ->and($game->arms)->toBeNull();
});

it('inicia la partida con dos jugadores y fija tres brazos', function () {
    Event::fake([GameStarted::class]);

    $game = makeGame();
    $token = newToken();
    addPlayer($game, 'Ana', 'red', $token, host: true);
    addPlayer($game, 'Beto', 'blue', newToken());
    $this->withUnencryptedCookie(PlayerSession::COOKIE_NAME, json_encode([$game->code => $token], JSON_THROW_ON_ERROR));

    $this->post("/rooms/{$game->code}/start")->assertRedirect();

    $game->refresh();

    expect($game->status)->toBe(GameStatus::InProgress)
        ->and($game->arms)->toBe(3)
        ->and($game->board_config['arms'])->toBe(3)
        ->and($game->board_config['occupiedArms'])->toBe([0, 1])
        ->and($game->started_at)->not->toBeNull();

    Event::assertDispatched(GameStarted::class);
});

it('prioriza el token de la cabecera sobre la cookie compartida entre pestañas', function () {
    $game = makeGame();
    $hostToken = newToken();
    $guestToken = newToken();
    addPlayer($game, 'Ana', 'red', $hostToken, host: true);
    addPlayer($game, 'Beto', 'blue', $guestToken);

    $this->withUnencryptedCookie(
        PlayerSession::COOKIE_NAME,
        json_encode([$game->code => $hostToken], JSON_THROW_ON_ERROR),
    )->withHeader(PlayerSession::TOKEN_HEADER, $guestToken)
        ->post("/rooms/{$game->code}/start")
        ->assertForbidden();

    expect($game->refresh()->status)->toBe(GameStatus::Waiting);
});

it('transfiere el rol de host cuando el host abandona la sala de espera', function () {
    Event::fake([PlayerLeft::class, HostChanged::class]);

    $game = makeGame();
    $hostToken = newToken();
    $host = addPlayer($game, 'Ana', 'red', $hostToken, host: true);
    $other = addPlayer($game, 'Beto', 'blue', newToken());
    $this->withUnencryptedCookie(PlayerSession::COOKIE_NAME, json_encode([$game->code => $hostToken], JSON_THROW_ON_ERROR));

    $this->post("/rooms/{$game->code}/leave")->assertRedirect('/');

    $game->refresh();

    expect($game->host_player_id)->toBe($other->id)
        ->and((bool) $other->refresh()->is_host)->toBeTrue()
        ->and($game->players()->count())->toBe(1)
        ->and($game->players()->first()->seat)->toBe(0);

    Event::assertDispatched(PlayerLeft::class);
    Event::assertDispatched(HostChanged::class);
});

it('genera el tablero de forma paramétrica para 3, 4 y 6 brazos', function () {
    expect(BoardFactory::armsForPlayers(2))->toBe(3)
        ->and(BoardFactory::armsForPlayers(3))->toBe(3)
        ->and(BoardFactory::armsForPlayers(4))->toBe(4)
        ->and(BoardFactory::armsForPlayers(5))->toBe(6)
        ->and(BoardFactory::armsForPlayers(6))->toBe(6);

    $board = BoardFactory::forArms(4, [0 => 'red', 1 => 'blue']);

    expect($board['cells'])->toHaveCount(52)
        ->and($board['safeCells'])->toBe([0, 6, 13, 19])
        ->and($board['cells'][0]['occupied'])->toBeTrue()
        ->and($board['cells'][26]['occupied'])->toBeFalse()
        ->and($board['armsDetail'][2]['occupied'])->toBeFalse();

    $star = BoardFactory::forArms(6, []);

    expect($star['geometry'])->toBe('star')
        ->and($star['cells'])->toHaveCount(60)
        ->and($star['safeCells'])->toBe([]);
});
