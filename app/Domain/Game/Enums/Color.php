<?php

namespace App\Domain\Game\Enums;

enum Color: string
{
    case Red = 'red';
    case Yellow = 'yellow';
    case Blue = 'blue';
    case Green = 'green';
    case Orange = 'orange';
    case Purple = 'purple';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $color): bool
    {
        return self::tryFrom($color) !== null;
    }
}
