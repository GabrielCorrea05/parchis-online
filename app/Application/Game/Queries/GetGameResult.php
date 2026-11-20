<?php

namespace App\Application\Game\Queries;

use App\Application\Game\Services\ResultBuilder;
use App\Models\Game;

final class GetGameResult
{
    public function __construct(private readonly ResultBuilder $builder) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Game $game): array
    {
        if (! $game->isFinished()) {
            // Nunca se filtran posiciones de una partida en curso.
            return [
                'game_code' => $game->code,
                'status' => 'en_curso',
                'board_arms' => $game->arms,
                'winner' => null,
                'ranking' => [],
                'message' => 'La partida aún está en curso.',
            ];
        }

        $payload = $game->result?->payload;

        if (! is_array($payload) || $payload === []) {
            $payload = $this->builder->build($game, $game->state ?? []);
        }

        // Un json decode con assoc=true convierte {} en []; el contrato exige objeto.
        $payload['captures_by_player'] = (object) ($payload['captures_by_player'] ?? []);

        return $payload;
    }
}
