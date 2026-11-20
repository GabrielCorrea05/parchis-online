<?php

namespace App\Infrastructure\Dice;

use App\Domain\Game\Services\DiceRoller;
use RuntimeException;

/**
 * Dado determinista para tests. Se registra en el contenedor con las tiradas
 * que debe devolver, en orden.
 */
final class FakeDiceRoller implements DiceRoller
{
    /** @var list<int> */
    private array $values;

    /**
     * @param  list<int>  $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function roll(): int
    {
        if ($this->values === []) {
            throw new RuntimeException('El dado falso no tiene más tiradas configuradas.');
        }

        $value = array_shift($this->values);

        if ($value < 1 || $value > 6) {
            throw new RuntimeException("Tirada falsa inválida: {$value}");
        }

        return $value;
    }
}
