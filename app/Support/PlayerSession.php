<?php

namespace App\Support;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Sustituye al login: un único cookie legible por el navegador guarda
 * el token de cada sala (código => token). Solo el hash se guarda en la BD.
 */
final class PlayerSession
{
    public const COOKIE_NAME = 'parchis_session';

    public const TOKEN_HEADER = 'X-Player-Token';

    private const MINUTES = 60 * 24 * 30;

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function remember(Player $player, string $token): void
    {
        $tokens = self::allTokens();
        $game = $player->game;

        if (! $game instanceof Game) {
            return;
        }

        $tokens[$game->code] = $token;

        self::queue($tokens);
    }

    public static function forget(string $code): void
    {
        $tokens = self::allTokens();
        unset($tokens[strtoupper($code)]);

        self::queue($tokens);
    }

    public static function tokenFor(?string $code = null, ?Request $request = null): ?string
    {
        $request ??= request();

        // La cabecera gana sobre la cookie: cada pestaña guarda su propio token
        // en sessionStorage y la cookie solo sirve para el primer arranque.
        $header = $request->header(self::TOKEN_HEADER);

        if ($header) {
            return $header;
        }

        $tokens = self::allTokens($request);

        if ($code !== null) {
            return $tokens[strtoupper($code)] ?? null;
        }

        return $tokens[array_key_first($tokens)] ?? null;
    }

    public static function playerFor(Game $game, ?Request $request = null): ?Player
    {
        $token = self::tokenFor($game->code, $request);

        if (! $token) {
            return null;
        }

        return $game->players()->where('token_hash', hash('sha256', $token))->first();
    }

    /** @return array<string, string> */
    private static function allTokens(?Request $request = null): array
    {
        $request ??= request();
        $raw = $request->cookie(self::COOKIE_NAME);

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, string> $tokens */
    private static function queue(array $tokens): void
    {
        Cookie::queue(
            self::COOKIE_NAME,
            json_encode($tokens, JSON_THROW_ON_ERROR),
            self::MINUTES,
            '/',
            null,
            false,
            false,
            'lax'
        );
    }
}
