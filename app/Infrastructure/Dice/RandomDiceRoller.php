<?php

namespace App\Infrastructure\Dice;

use App\Domain\Game\Services\DiceRoller;

final class RandomDiceRoller implements DiceRoller
{
    public function roll(): int
    {
        return random_int(1, 6);
    }
}
