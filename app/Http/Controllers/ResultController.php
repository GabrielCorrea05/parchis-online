<?php

namespace App\Http\Controllers;

use App\Application\Game\Queries\GetGameResult;
use App\Domain\Game\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameResult;
use Inertia\Inertia;
use Inertia\Response;

final class ResultController extends Controller
{
    public function __construct(private readonly GetGameResult $results) {}

    public function index(): Response
    {
        $history = GameResult::with(['game', 'winner'])
            ->get()
            ->sortByDesc(fn (GameResult $result): int => (int) ($result->game->finished_at->timestamp ?? 0))
            ->map(fn (GameResult $result): array => [
                'code' => $result->game->code,
                'winner' => [
                    'nickname' => $result->winner->nickname ?? '—',
                    'color' => $result->winner->color ?? '',
                ],
                'playerCount' => count($result->ranking),
                'durationSeconds' => $result->duration_seconds,
                'finishedAt' => $result->game->finished_at?->toJSON(),
            ])
            ->values()
            ->all();

        return Inertia::render('Results/Index', ['history' => $history]);
    }

    public function show(string $code): Response
    {
        $game = Game::where('code', strtoupper($code))
            ->where('status', GameStatus::Finished)
            ->firstOrFail();

        return Inertia::render('Results/Show', ['result' => $this->results->handle($game)]);
    }
}
