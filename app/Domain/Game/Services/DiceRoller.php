<?php

namespace App\Domain\Game\Services;

interface DiceRoller
{
    /**
     * Devuelve siempre un valor entre 1 y 6. El motor nunca genera el dado.
     */
    public function roll(): int;
}
