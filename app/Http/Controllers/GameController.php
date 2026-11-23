<?php

namespace App\Http\Controllers;

use App\Application\Game\Exceptions\ActionConflictException;
use App\Application\Game\Exceptions\ForbiddenPlayerException;
use App\Application\Game\Exceptions\GameActionRejected;
use App\Application\Game\Exceptions\StaleVersionException;
use App\Application\Game\Exceptions\UnauthorizedPlayerException;
use App\Application\Game\Queries\GetGameResult;
use App\Application\Game\Queries\GetGameSnapshot;
use App\Application\Game\Services\GameService;
use App\Models\Game;
use App\Support\PlayerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class GameController extends Controller
{
    public function __construct(
        private readonly GameService $games,
        private readonly GetGameSnapshot $snapshots,
        private readonly GetGameResult $results,
    ) {}

    public function show(string $code, Request $request): JsonResponse
    {
        return $this->respond(function () use ($code, $request): array {
            $game = $this->findGame($code);
            $player = $this->games->authenticate($game, PlayerSession::tokenFor($code, $request));

            return $this->snapshots->handle($game->load('players'), $player)->toArray();
        });
    }

    public function roll(string $code, Request $request): JsonResponse
    {
        return $this->respond(function () use ($code, $request): array {
            $game = $this->findGame($code);
            $player = $this->games->authenticate($game, PlayerSession::tokenFor($code, $request));
            $version = $request->validate(['version' => 'sometimes|integer|min:0'])['version'] ?? null;

            $outcome = $this->games->roll($game, $player, $version === null ? null : (int) $version);
            $this->dispatchBroadcasts($outcome['broadcasts']);

            Log::info('game.roll', [
                'game_id' => $game->id,
                'game_code' => $game->code,
                'player_id' => $player->id,
                'version' => $version === null ? null : (int) $version,
                'roll' => $outcome['snapshot']['state']['dice']['last'] ?? null,
                'consecutive_sixes' => $outcome['snapshot']['state']['dice']['consecutiveSixes'] ?? null,
                'pending_roll' => $outcome['snapshot']['pendingRoll'] ?? null,
            ]);

            return $outcome['snapshot'];
        });
    }

    public function move(string $code, Request $request): JsonResponse
    {
        return $this->respond(function () use ($code, $request): array {
            $game = $this->findGame($code);
            $player = $this->games->authenticate($game, PlayerSession::tokenFor($code, $request));
            $validated = $request->validate([
                'pieceId' => 'required|string',
                'version' => 'sometimes|integer|min:0',
            ]);
            $version = $validated['version'] ?? null;

            $outcome = $this->games->move(
                $game,
                $player,
                (string) $validated['pieceId'],
                $version === null ? null : (int) $version,
            );
            $this->dispatchBroadcasts($outcome['broadcasts']);

            return $outcome['snapshot'];
        });
    }

    public function result(string $code, Request $request): JsonResponse
    {
        return $this->respond(function () use ($code, $request): array {
            $game = $this->findGame($code);
            $this->games->authenticate($game, PlayerSession::tokenFor($code, $request));

            return $this->results->handle($game);
        });
    }

    public function rematch(string $code, Request $request): JsonResponse
    {
        return $this->respond(function () use ($code, $request): array {
            $game = $this->findGame($code);
            $player = $this->games->authenticate($game, PlayerSession::tokenFor($code, $request));

            $outcome = $this->games->rematch($game, $player);
            $this->dispatchBroadcasts($outcome['broadcasts']);

            return [
                'newCode' => $outcome['newCode'],
                'message' => $outcome['message'],
            ];
        });
    }

    public function rematchToken(string $code, Request $request): JsonResponse
    {
        return $this->respond(function () use ($code, $request): array {
            $game = $this->findGame($code);
            $token = PlayerSession::tokenFor($code, $request);
            $this->games->authenticate($game, $token);

            return $this->games->rematchToken($game, (string) $token);
        });
    }

    private function findGame(string $code): Game
    {
        return Game::where('code', strtoupper($code))->firstOrFail();
    }

    /** @param  list<object>  $events */
    private function dispatchBroadcasts(array $events): void
    {
        foreach ($events as $event) {
            try {
                event($event);
            } catch (\Throwable $exception) {
                // Reverb caído no debe romper la acción del usuario.
                report($exception);
            }
        }
    }

    /** @param  callable(): array<string, mixed>  $callback */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback());
        } catch (UnauthorizedPlayerException $exception) {
            return $this->error(401, $exception->getMessage());
        } catch (ForbiddenPlayerException $exception) {
            return $this->error(403, $exception->getMessage());
        } catch (ActionConflictException|StaleVersionException $exception) {
            return $this->error(409, $exception->getMessage());
        } catch (GameActionRejected $exception) {
            return $this->error(422, $exception->getMessage());
        }
    }

    private function error(int $status, string $message): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
