<?php

namespace App\Http\Controllers;

use App\Application\Game\Exceptions\GameActionRejected;
use App\Application\Game\Queries\GetRoomView;
use App\Application\Game\Services\GameService;
use App\Application\Game\Services\RoomCodeGenerator;
use App\Domain\Game\Enums\GameStatus;
use App\Http\Requests\CreateRoomRequest;
use App\Http\Requests\JoinRoomRequest;
use App\Infrastructure\Broadcasting\GameStarted;
use App\Infrastructure\Broadcasting\HostChanged;
use App\Infrastructure\Broadcasting\PlayerJoined;
use App\Infrastructure\Broadcasting\PlayerLeft;
use App\Models\Game;
use App\Models\Player;
use App\Support\PlayerSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RoomController extends Controller
{
    public function __construct(
        private readonly GetRoomView $getRoomView,
        private readonly GameService $games,
        private readonly RoomCodeGenerator $codes,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Welcome', [
            'colors' => $this->colorOptions(),
            'maxPlayers' => config('parchis.max_players'),
        ]);
    }

    public function store(CreateRoomRequest $request): RedirectResponse
    {
        $code = $this->generateCode();
        $token = PlayerSession::generateToken();

        $game = DB::transaction(function () use ($request, $code, $token): Game {
            $game = Game::create([
                'code' => $code,
                'status' => GameStatus::Waiting,
                'version' => 0,
            ]);

            $host = $this->addPlayer($game, $request->validated('nickname'), $request->validated('color'), $token, true);

            $game->host_player_id = $host->id;
            $game->save();

            return $game;
        });

        $host = $game->players()->firstOrFail();
        PlayerSession::remember($host, $token);

        $this->announce(PlayerJoined::class, $code, $game, $host, "{$host->nickname} ha creado la sala.");

        return to_route('rooms.show', $code);
    }

    public function show(string $code): Response
    {
        $game = $this->findGame($code);
        $player = PlayerSession::playerFor($game);

        if ($player === null) {
            return Inertia::render('Rooms/Join', [
                'code' => $game->code,
                'colors' => $this->colorOptions(),
                'maxPlayers' => config('parchis.max_players'),
            ]);
        }

        return Inertia::render('Rooms/Waiting', [
            'room' => $this->roomView($game, $player),
        ]);
    }

    public function join(JoinRoomRequest $request, string $code): RedirectResponse
    {
        $game = $this->findGame($code);
        $token = PlayerSession::generateToken();

        if (! $game->isWaiting()) {
            return back()->withErrors(['code' => 'La partida ya ha empezado.']);
        }

        if ($game->players()->count() >= config('parchis.max_players')) {
            return back()->withErrors(['code' => 'La sala está llena.']);
        }

        $player = DB::transaction(function () use ($game, $request, $token): Player {
            $fresh = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

            if (! $fresh->isWaiting()) {
                throw ValidationException::withMessages(['code' => 'La partida ya ha empezado.']);
            }

            if ($fresh->players()->count() >= config('parchis.max_players')) {
                throw ValidationException::withMessages(['code' => 'La sala está llena.']);
            }

            return $this->addPlayer(
                $fresh,
                $request->validated('nickname'),
                $request->validated('color'),
                $token
            );
        });

        PlayerSession::remember($player, $token);

        $game->refresh();
        $this->announce(PlayerJoined::class, $game->code, $game, $player, "{$player->nickname} se ha unido a la sala.");

        return to_route('rooms.show', $game->code);
    }

    public function leave(string $code): RedirectResponse
    {
        $game = $this->findGame($code);
        $player = PlayerSession::playerFor($game);

        if ($player === null) {
            return to_route('home');
        }

        $wasHost = false;
        $nickname = $player->nickname;

        DB::transaction(function () use ($game, $player, &$wasHost): void {
            $fresh = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $current = $fresh->players()->find($player->id);

            if ($current === null) {
                return;
            }

            $wasHost = $fresh->host_player_id === $current->id;
            $current->delete();

            foreach ($fresh->players()->orderBy('seat')->get() as $index => $remaining) {
                $remaining->update(['seat' => $index, 'is_host' => false]);
            }

            if ($wasHost) {
                $newHost = $fresh->players()->orderBy('seat')->first();

                if ($newHost !== null) {
                    $fresh->update(['host_player_id' => $newHost->id]);
                    $newHost->update(['is_host' => true]);
                } else {
                    $fresh->update(['host_player_id' => null]);
                }
            }
        });

        PlayerSession::forget($game->code);
        $game->refresh();

        $this->announce(PlayerLeft::class, $game->code, $game, null, "{$nickname} ha salido de la sala.");

        if ($wasHost) {
            $newHost = $game->players()->orderBy('seat')->first();
            $message = $newHost !== null
                ? "{$newHost->nickname} es el nuevo host."
                : 'No quedan jugadores en la sala.';
            $this->announce(HostChanged::class, $game->code, $game, null, $message);
        }

        return to_route('home');
    }

    public function start(string $code): RedirectResponse
    {
        $game = $this->findGame($code);
        $player = PlayerSession::playerFor($game);

        if ($player === null || ! $player->isHostOf($game)) {
            abort(403, 'Solo el host puede iniciar la partida.');
        }

        if (! $game->isWaiting()) {
            return back()->withErrors(['start' => 'La partida ya está iniciada.']);
        }

        $playerCount = $game->players()->count();

        if ($playerCount < config('parchis.min_players') || $playerCount > config('parchis.max_players')) {
            return back()->withErrors(['start' => 'Se necesitan entre 2 y 6 jugadores.']);
        }

        try {
            $outcome = $this->games->start($game, $player);
        } catch (GameActionRejected $exception) {
            return back()->withErrors(['start' => $exception->getMessage()]);
        }

        $game->refresh();
        $game->load('players');

        $this->announce(
            GameStarted::class,
            $game->code,
            $game,
            $player,
            "¡Partida iniciada! Tablero fijado con {$outcome['snapshot']['arms']} brazos.",
        );

        $this->dispatchBroadcasts($outcome['broadcasts']);

        return back();
    }

    private function findGame(string $code): Game
    {
        return Game::where('code', strtoupper($code))->firstOrFail();
    }

    private function addPlayer(Game $game, string $nickname, string $color, string $token, bool $isHost = false): Player
    {
        if ($game->players()->where('color', $color)->exists()) {
            throw ValidationException::withMessages(['color' => 'Ese color ya está ocupado en la sala.']);
        }

        $maxSeat = $game->players()->max('seat');

        return $game->players()->create([
            'nickname' => $nickname,
            'color' => $color,
            'token_hash' => hash('sha256', $token),
            'is_host' => $isHost,
            'is_connected' => true,
            'seat' => $maxSeat === null ? 0 : $maxSeat + 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function roomView(Game $game, ?Player $player): array
    {
        return $this->getRoomView->handle($game, $player)->toArray();
    }

    /**
     * La sala ya está persistida cuando se emite el evento: si Reverb no está
     * disponible, se registra el fallo pero no se rompe la acción del usuario.
     */
    private function announce(string $event, string $code, Game $game, ?Player $player, string $message): void
    {
        try {
            $event::dispatch($code, $this->roomView($game, $player), $message);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** @param  list<object>  $events */
    private function dispatchBroadcasts(array $events): void
    {
        foreach ($events as $event) {
            try {
                event($event);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    private function generateCode(): string
    {
        return $this->codes->generate();
    }

    /** @return list<array{name: string, hex: string}> */
    private function colorOptions(): array
    {
        $colors = config('parchis.colors');
        $options = [];

        foreach (is_array($colors) ? $colors : [] as $name => $hex) {
            $options[] = ['name' => (string) $name, 'hex' => (string) $hex];
        }

        return $options;
    }
}
