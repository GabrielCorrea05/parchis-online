<?php

namespace App\Application\Game\DTOs;

/**
 * Snapshot autoritativo que el cliente solo dibuja: nunca calcula reglas.
 */
final readonly class GameSnapshot
{
    /**
     * @param  array<string, mixed>  $board
     * @param  list<array<string, mixed>>  $players
     * @param  array<string, mixed>  $state
     * @param  list<string>  $legalMoves
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        public int $gameId,
        public string $code,
        public string $status,
        public int $arms,
        public array $board,
        public array $players,
        public ?int $youPlayerId,
        public array $state,
        public int $version,
        public ?int $currentPlayerId,
        public ?int $pendingRoll,
        public array $legalMoves,
        public ?array $result,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'gameId' => $this->gameId,
            'code' => $this->code,
            'status' => $this->status,
            'arms' => $this->arms,
            'board' => $this->board,
            'players' => $this->players,
            'youPlayerId' => $this->youPlayerId,
            'state' => $this->state,
            'version' => $this->version,
            'currentPlayerId' => $this->currentPlayerId,
            'pendingRoll' => $this->pendingRoll,
            'legalMoves' => $this->legalMoves,
            'result' => $this->result,
        ];
    }
}
