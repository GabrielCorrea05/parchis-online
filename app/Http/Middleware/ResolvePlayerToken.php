<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Support\PlayerSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sin login, el "usuario" de Laravel para los canales se resuelve
 * desde el token guardado en la cookie de la sala.
 */
final class ResolvePlayerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->setUserResolver(function () use ($request) {
            $channel = $request->input('channel_name');

            if (! is_string($channel)) {
                return null;
            }

            if (preg_match('/^private-game\.(\d+)$/', $channel, $matches)) {
                $game = Game::find((int) $matches[1]);

                return $game === null ? null : PlayerSession::playerFor($game, $request);
            }

            if (! preg_match('/^presence-room\.([^.]+)$/', $channel, $matches)) {
                return null;
            }

            $game = Game::where('code', strtoupper($matches[1]))->first();

            return $game === null ? null : PlayerSession::playerFor($game, $request);
        });

        return $next($request);
    }
}
