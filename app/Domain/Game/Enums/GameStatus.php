<?php

namespace App\Domain\Game\Enums;

enum GameStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Finished = 'finished';

    public function isWaiting(): bool
    {
        return $this === self::Waiting;
    }

    public function isInProgress(): bool
    {
        return $this === self::InProgress;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
