<?php

namespace App\Http\Controllers;

use App\Application\Game\Queries\GetGameResult;
use App\Application\Game\Queries\GetGameSnapshot;
use App\Models\Game;
use App\Support\PlayerSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class GameViewController extends Controller
{
    public function __construct(
        private readonly GetGameSnapshot $snapshots,
        private readonly GetGameResult $results,
    ) {}

    public function show(string $code): Response|RedirectResponse
    {
        $game = Game::where('code', strtoupper($code))->firstOrFail();
        $player = PlayerSession::playerFor($game);

        if ($player === null || $game->isWaiting()) {
            return to_route('rooms.show', $game->code);
        }

        $snapshot = $this->snapshots->handle($game->load('players'), $player)->toArray();

        if ($game->isFinished()) {
            return Inertia::render('Games/Result', [
                'result' => $this->results->handle($game),
                'snapshot' => $snapshot,
            ]);
        }

        return Inertia::render('Games/Board', ['snapshot' => $snapshot]);
    }
}
