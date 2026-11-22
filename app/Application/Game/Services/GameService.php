<?php

namespace App\Application\Game\Services;

use App\Application\Game\Exceptions\ActionConflictException;
use App\Application\Game\Exceptions\ForbiddenPlayerException;
use App\Application\Game\Exceptions\GameActionRejected;
use App\Application\Game\Exceptions\StaleVersionException;
use App\Application\Game\Exceptions\UnauthorizedPlayerException;
use App\Application\Game\Queries\GetGameSnapshot;
use App\Domain\Game\Enums\GameStatus;
use App\Domain\Game\Exceptions\GameRuleViolation;
use App\Domain\Game\Services\BoardFactory;
use App\Domain\Game\Services\DiceRoller;
use App\Domain\Game\Services\GameEngine;
use App\Infrastructure\Broadcasting\CaptureOccurred;
use App\Infrastructure\Broadcasting\DiceRolled;
use App\Infrastructure\Broadcasting\GameFinished;
use App\Infrastructure\Broadcasting\GameStartedForGame;
use App\Infrastructure\Broadcasting\GameStateUpdated;
use App\Infrastructure\Broadcasting\PieceMoved;
use App\Infrastructure\Broadcasting\RematchCreated;
use App\Infrastructure\Broadcasting\TurnAdvanced;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta las acciones autoritativas: bloqueo, transacción, motor, snapshot,
 * log de eventos y broadcast (los eventos se devuelven para emitirlos tras commit).
 */
final class GameService
{
    public function __construct(
        private readonly DiceRoller $dice,
        private readonly GetGameSnapshot $snapshots,
        private readonly ResultBuilder $results,
        private readonly RoomCodeGenerator $codes,
    ) {}

    public function authenticate(Game $game, ?string $token): Player
    {
        if ($token === null || $token === '') {
            throw new UnauthorizedPlayerException('Falta el token de jugador.');
        }

        $hash = hash('sha256', $token);
        $player = $game->players()->where('token_hash', $hash)->first();

        if ($player !== null) {
            return $player;
        }

        if (Player::where('token_hash', $hash)->exists()) {
            throw new ForbiddenPlayerException('Ese token pertenece a otra partida.');
        }

        throw new UnauthorizedPlayerException('El token de jugador no es válido.');
    }

    /**
     * Inicia la partida, fija el tablero y asienta a cada jugador según su color.
     *
     * @return array{snapshot: array<string, mixed>, broadcasts: list<object>}
     */
    public function start(Game $game, Player $host): array
    {
        /** @var list<object> $broadcasts */
        $broadcasts = [];

        $snapshot = $this->withLock($game, function (Game $fresh) use ($host, &$broadcasts): array {
            if (! $fresh->isWaiting()) {
                throw new GameActionRejected('La partida ya está iniciada.');
            }

            if ($fresh->host_player_id !== $host->id) {
                throw new ForbiddenPlayerException('Solo el host puede iniciar la partida.');
            }

            $members = $fresh->players()->orderBy('id')->get();
            $count = $members->count();

            if ($count < config('parchis.min_players') || $count > config('parchis.max_players')) {
                throw new GameActionRejected('Se necesitan entre 2 y 6 jugadores.');
            }

            $arms = BoardFactory::armsForPlayers($count);
            $this->assignSeatsAndColors($members, $arms);
            $members = $members->sortBy('seat')->values();

            $colorsBySeat = [];
            $playersForEngine = [];

            foreach ($members as $member) {
                $colorsBySeat[(int) $member->seat] = (string) $member->color;
                $playersForEngine[] = [
                    'id' => (int) $member->id,
                    'nickname' => (string) $member->nickname,
                    'color' => (string) $member->color,
                    'seat' => (int) $member->seat,
                    'isConnected' => (bool) $member->is_connected,
                ];
            }

            $board = BoardFactory::forArms($arms, $colorsBySeat);
            $state = GameEngine::initialState($playersForEngine, (int) $host->id);
            $message = "¡Partida iniciada! Tablero fijado con {$arms} brazos.";

            $fresh->update([
                'status' => GameStatus::InProgress,
                'arms' => $arms,
                'board_config' => $board,
                'state' => $state,
                'started_at' => now(),
                'version' => $fresh->version + 1,
            ]);

            $this->appendEvents($fresh, [[
                'type' => 'game_started',
                'payload' => [
                    'arms' => $arms,
                    'hostPlayerId' => (int) $host->id,
                    'version' => (int) $fresh->version,
                    'players' => $playersForEngine,
                ],
            ]]);

            $snapshot = $this->snapshotOf($fresh, $host);
            $broadcasts[] = new GameStartedForGame($fresh->code, $snapshot, $message);

            return $snapshot;
        });

        return ['snapshot' => $snapshot, 'broadcasts' => $broadcasts];
    }

    /**
     * @return array{snapshot: array<string, mixed>, broadcasts: list<object>}
     */
    public function roll(Game $game, Player $player, ?int $version): array
    {
        /** @var list<object> $broadcasts */
        $broadcasts = [];

        $snapshot = $this->withLock($game, function (Game $fresh) use ($player, $version, &$broadcasts): array {
            $this->assertVersion($fresh, $version);
            [$state, $board] = $this->assertActive($fresh, $player, true);

            $engine = new GameEngine($board);

            try {
                $outcome = $engine->roll($this->dice->roll(), $state);
            } catch (GameRuleViolation $exception) {
                throw new GameActionRejected($exception->getMessage());
            }

            return $this->persist($fresh, $player, $outcome, 'roll', $broadcasts);
        });

        return ['snapshot' => $snapshot, 'broadcasts' => $broadcasts];
    }

    /**
     * @return array{snapshot: array<string, mixed>, broadcasts: list<object>}
     */
    public function move(Game $game, Player $player, string $pieceId, ?int $version): array
    {
        /** @var list<object> $broadcasts */
        $broadcasts = [];

        $snapshot = $this->withLock($game, function (Game $fresh) use ($player, $pieceId, $version, &$broadcasts): array {
            $this->assertVersion($fresh, $version);
            [$state, $board] = $this->assertActive($fresh, $player, true);

            if ($pieceId === '') {
                throw new GameActionRejected('Debes indicar la ficha que quieres mover.');
            }

            $engine = new GameEngine($board);

            try {
                $outcome = $engine->move($pieceId, $state);
            } catch (GameRuleViolation $exception) {
                throw new GameActionRejected($exception->getMessage());
            }

            return $this->persist($fresh, $player, $outcome, 'move', $broadcasts);
        });

        return ['snapshot' => $snapshot, 'broadcasts' => $broadcasts];
    }

    /**
     * @return array{newCode: string, message: string, broadcasts: list<object>}
     */
    public function rematch(Game $game, Player $player): array
    {
        /** @var list<object> $broadcasts */
        $broadcasts = [];
        $message = 'Nueva sala creada.';

        $newCode = $this->withLock($game, function (Game $fresh) use ($player, $message, &$broadcasts): string {
            if (! $fresh->isFinished()) {
                throw new GameActionRejected('Solo se puede crear una revancha cuando la partida ha terminado.');
            }

            if ($fresh->host_player_id !== $player->id) {
                throw new ForbiddenPlayerException('Solo el host puede crear la revancha.');
            }

            $newCode = $this->codes->generate();
            $newGame = Game::create([
                'code' => $newCode,
                'status' => GameStatus::Waiting,
                'version' => 0,
            ]);

            foreach ($fresh->players()->orderBy('seat')->get() as $oldPlayer) {
                $newGame->players()->create([
                    'nickname' => $oldPlayer->nickname,
                    'color' => $oldPlayer->color,
                    'token_hash' => $oldPlayer->token_hash,
                    'is_host' => $oldPlayer->id === $fresh->host_player_id,
                    'is_connected' => true,
                    'seat' => $oldPlayer->seat,
                ]);
            }

            $newHost = $newGame->players()->where('is_host', true)->first();
            $newGame->update(['host_player_id' => $newHost?->id]);

            $this->appendEvents($fresh, [[
                'type' => 'rematch_created',
                'payload' => [
                    'oldCode' => $fresh->code,
                    'newCode' => $newCode,
                    'hostPlayerId' => (int) $fresh->host_player_id,
                ],
            ]]);

            $broadcasts[] = new RematchCreated(
                (int) $fresh->id,
                $fresh->code,
                $newCode,
                $message,
            );

            return $newCode;
        });

        return ['newCode' => $newCode, 'message' => $message, 'broadcasts' => $broadcasts];
    }

    /**
     * @return array{code: string, playerToken: string}
     */
    public function rematchToken(Game $game, string $token): array
    {
        $event = $game->events()
            ->where('type', 'rematch_created')
            ->orderByDesc('sequence')
            ->first();

        $newCode = is_array($event?->payload) ? ($event->payload['newCode'] ?? null) : null;

        if (! is_string($newCode)) {
            throw new GameActionRejected('Todavía no hay una sala de revancha.');
        }

        $newGame = Game::where('code', $newCode)->where('status', GameStatus::Waiting)->first();

        if ($newGame === null) {
            throw new GameActionRejected('La sala de revancha ya no está disponible.');
        }

        return ['code' => $newCode, 'playerToken' => $token];
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @param  list<object>  $broadcasts
     * @return array<string, mixed>
     */
    private function persist(
        Game $game,
        Player $player,
        array $outcome,
        string $action,
        array &$broadcasts,
    ): array {
        /** @var array<string, mixed> $state */
        $state = $outcome['state'];
        /** @var list<array<string, mixed>> $domainEvents */
        $domainEvents = $outcome['events'];
        $finished = ($state['status'] ?? null) === 'finished';

        $attributes = [
            'state' => $state,
            'version' => $game->version + 1,
        ];

        if ($finished) {
            $attributes['status'] = GameStatus::Finished;
            $attributes['winner_player_id'] = $state['winnerPlayerId'] ?? null;
            $attributes['finished_at'] = now();
        }

        $game->update($attributes);

        $this->appendEvents($game, array_map(
            fn (array $event): array => [
                'type' => (string) ($event['type'] ?? 'state_updated'),
                'payload' => [
                    'event' => $event,
                    'snapshot' => [
                        'version' => (int) $game->version,
                        'status' => (string) ($state['status'] ?? 'in_progress'),
                        'currentPlayerId' => $state['currentPlayerId'] ?? null,
                        'pendingRoll' => $state['pendingRoll'] ?? null,
                        'turns' => $state['turns'] ?? 0,
                    ],
                ],
            ],
            $domainEvents,
        ));

        $result = $finished ? $this->persistResult($game, $state) : null;

        $types = array_column($domainEvents, 'type');
        $reason = match (true) {
            in_array('game_finished', $types, true) => 'finish',
            in_array('third_six', $types, true) => 'third_six',
            in_array('no_legal_move', $types, true) => 'no_legal_move',
            in_array('extra_turn', $types, true) => 'extra_turn',
            default => $action,
        };

        $snapshot = $this->snapshotOf($game, $player);
        $gameId = (int) $game->id;

        foreach ($domainEvents as $event) {
            $type = (string) ($event['type'] ?? '');

            if ($type === 'dice_rolled') {
                $broadcasts[] = new DiceRolled(
                    $gameId,
                    $snapshot,
                    (int) ($event['playerId'] ?? 0),
                    (int) ($event['value'] ?? 0),
                    (int) ($event['consecutiveSixes'] ?? 0),
                );
            } elseif ($type === 'piece_out') {
                $broadcasts[] = new PieceMoved(
                    $gameId,
                    $snapshot,
                    (string) ($event['pieceId'] ?? ''),
                    (int) ($event['playerId'] ?? 0),
                    null,
                    0,
                );
            } elseif ($type === 'piece_moved') {
                $broadcasts[] = new PieceMoved(
                    $gameId,
                    $snapshot,
                    (string) ($event['pieceId'] ?? ''),
                    (int) ($event['playerId'] ?? 0),
                    (int) ($event['from'] ?? 0),
                    (int) ($event['to'] ?? 0),
                );
            } elseif ($type === 'capture') {
                $broadcasts[] = new CaptureOccurred(
                    $gameId,
                    $snapshot,
                    (int) ($event['playerId'] ?? 0),
                    array_values((array) ($event['captured'] ?? [])),
                );
            } elseif ($type === 'turn_advanced') {
                $broadcasts[] = new TurnAdvanced(
                    $gameId,
                    $snapshot,
                    (int) ($event['fromPlayerId'] ?? 0),
                    (int) ($event['playerId'] ?? 0),
                    $reason,
                );
            } elseif ($type === 'game_finished' && is_array($result)) {
                $broadcasts[] = new GameFinished($gameId, $snapshot, $result);
            }
        }

        $broadcasts[] = new GameStateUpdated($gameId, $snapshot, $reason);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function persistResult(Game $game, array $state): array
    {
        $payload = $this->results->build($game, $state);

        GameResult::updateOrCreate(['game_id' => $game->id], [
            'winner_player_id' => $state['winnerPlayerId'] ?? null,
            'board_arms' => (int) ($game->arms ?? 0),
            'duration_seconds' => (int) ($payload['duration_seconds'] ?? 0),
            'turns' => (int) ($payload['turns'] ?? 0),
            'total_rolls' => (int) ($payload['total_rolls'] ?? 0),
            'total_captures' => (int) ($payload['total_captures'] ?? 0),
            'ranking' => $payload['ranking'] ?? [],
            'payload' => $payload,
            'finished_at' => $game->finished_at,
        ]);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function snapshotOf(Game $game, ?Player $current): array
    {
        $game->unsetRelation('players');

        return $this->snapshots->handle($game->load('players'), $current)->toArray();
    }

    /**
     * El color elegido fija el brazo: amarillo=5, azul=22, rojo=39, verde=56.
     * El host sigue empezando porque `GameEngine::initialState` rota el orden
     * de turnos para ponerlo primero aunque su asiento no sea el 0.
     *
     * @param  iterable<int, Player>  $members
     */
    private function assignSeatsAndColors(iterable $members, int $arms): void
    {
        $colors = [];

        foreach ($members as $member) {
            $colors[] = (string) $member->color;
        }

        $assignments = BoardFactory::seatAssignments($colors, $arms);

        foreach ($members as $position => $member) {
            $assignment = $assignments[$position];

            if ((int) $member->seat !== $assignment['seat'] || (string) $member->color !== $assignment['color']) {
                $member->update([
                    'seat' => $assignment['seat'],
                    'color' => $assignment['color'],
                ]);
            }
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array{arms: int, cellsPerArm: int, homeLength: int, safeCells?: list<int>, startIndices?: list<int>}}
     */
    private function assertActive(Game $game, Player $player, bool $checkTurn): array
    {
        if (! $game->isInProgress()) {
            throw new GameActionRejected('La partida no está en curso.');
        }

        if ($game->players()->whereKey($player->id)->doesntExist()) {
            throw new ForbiddenPlayerException('No perteneces a esta partida.');
        }

        $state = $game->state;
        $board = $game->board_config;

        if (! is_array($state) || $state === []) {
            throw new GameActionRejected('El estado de la partida no está disponible.');
        }

        if ($checkTurn && (int) ($state['currentPlayerId'] ?? 0) !== (int) $player->id) {
            throw new GameActionRejected('No es tu turno.');
        }

        return [$state, GameBoard::forEngine($board)];
    }

    private function assertVersion(Game $game, ?int $version): void
    {
        if ($version !== null && $version !== (int) $game->version) {
            throw new StaleVersionException('La partida ha cambiado. Recarga el tablero para continuar.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(Game): T  $callback
     * @return T
     */
    private function withLock(Game $game, callable $callback): mixed
    {
        $lock = Cache::lock("game:{$game->id}:action", 10);

        if (! $lock->block(5)) {
            throw new ActionConflictException('La partida está ocupada. Inténtalo de nuevo.');
        }

        try {
            return DB::transaction(function () use ($game, $callback): mixed {
                $fresh = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

                return $callback($fresh);
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<array{type: string, payload: array<string, mixed>}>  $events
     */
    private function appendEvents(Game $game, array $events): void
    {
        $sequence = (int) ($game->events()->max('sequence') ?? 0);

        foreach ($events as $event) {
            $game->events()->create([
                'sequence' => ++$sequence,
                'type' => $event['type'],
                'payload' => $event['payload'],
            ]);
        }
    }
}
