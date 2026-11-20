<?php

namespace App\Application\Game\DTOs;

final readonly class RoomView
{
    /**
     * @param  array<string, mixed>  $board
     * @param  array<int, array<string, mixed>>  $players
     */
    public function __construct(
        public string $code,
        public string $status,
        public ?int $arms,
        public array $board,
        public array $players,
        public ?int $hostPlayerId,
        public int $playerCount,
        public int $minPlayers,
        public int $maxPlayers,
        public ?int $currentPlayerId,
        public bool $canStart,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
