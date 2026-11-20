<?php

namespace App\Domain\Game\Services;

use InvalidArgumentException;

/**
 * Única fuente de verdad para la geometría del tablero.
 *
 * El tablero NO se escribe tres veces: se genera a partir del número de brazos.
 * El cliente recibe este array y solo lo dibuja.
 */
final class BoardFactory
{
    public static function armsForPlayers(int $players): int
    {
        return match (true) {
            $players >= 2 && $players <= 3 => 3,
            $players === 4 => 4,
            $players >= 5 && $players <= 6 => 6,
            default => throw new InvalidArgumentException("Número de jugadores no soportado: {$players}"),
        };
    }

    /**
     * @param  array<int, string>  $colors  Color por brazo ocupado (índice de brazo => color).
     * @return array<string, mixed>
     */
    public static function forArms(int $arms, array $colors = []): array
    {
        $boards = config('parchis.boards');

        if (! isset($boards[$arms])) {
            throw new InvalidArgumentException("Número de brazos no soportado: {$arms}");
        }

        $cellsPerArm = (int) $boards[$arms]['cells_per_arm'];
        $geometry = $boards[$arms]['geometry'];
        $totalCells = $arms * $cellsPerArm;

        $center = config('parchis.center');
        $outer = (float) config('parchis.outer_radius');
        $inner = (float) config('parchis.inner_radius');
        $cellSize = (int) config('parchis.cell_size');
        $homeLength = (int) config('parchis.home_length');
        $palette = config('parchis.colors');

        $armsDetail = [];
        for ($arm = 0; $arm < $arms; $arm++) {
            $angle = -90 + ($arm * 360 / $arms);
            $color = $colors[$arm] ?? array_key_first($palette);
            $armsDetail[] = [
                'index' => $arm,
                'angle' => $angle,
                'color' => $color,
                'hex' => $palette[$color] ?? '#64748b',
                'occupied' => array_key_exists($arm, $colors),
                'salidaIndex' => $arm * $cellsPerArm,
                'base' => self::point($center, $angle, $outer + 70),
                'entrance' => self::point($center, $angle, self::radiusForArm($arm, $arms, $outer, $inner, $geometry)),
            ];
        }

        $bases = [];
        $homes = [];
        foreach ($armsDetail as $arm) {
            $angle = (float) $arm['angle'];
            $base = $arm['base'];
            $bases[] = [
                'arm' => $arm['index'],
                'color' => $arm['color'],
                'hex' => $arm['hex'],
                'occupied' => $arm['occupied'],
                'slots' => [
                    self::offset($base, -20, -20),
                    self::offset($base, 20, -20),
                    self::offset($base, -20, 20),
                    self::offset($base, 20, 20),
                ],
            ];

            $entranceRadius = self::distance($center, $arm['entrance']);
            $homeCells = [];
            for ($step = 0; $step < $homeLength; $step++) {
                $radius = max(62.0, $entranceRadius - 26 - $step * (($entranceRadius - 100) / max(1, $homeLength)));
                $homeCells[] = self::point($center, $angle, $radius);
            }

            $goals = [];
            for ($slot = 0; $slot < 4; $slot++) {
                $goalAngle = $angle - 18 + ($slot * 12);
                $goals[] = self::point($center, $goalAngle, 42);
            }

            $homes[] = [
                'arm' => $arm['index'],
                'color' => $arm['color'],
                'hex' => $arm['hex'],
                'occupied' => $arm['occupied'],
                'cells' => $homeCells,
                'goals' => $goals,
            ];
        }

        $cells = [];
        for ($index = 0; $index < $totalCells; $index++) {
            $arm = intdiv($index, $cellsPerArm);
            $progressInsideArm = ($index % $cellsPerArm) / $cellsPerArm;

            $from = self::radiusForArm($arm, $arms, $outer, $inner, $geometry);
            $to = self::radiusForArm(($arm + 1) % $arms, $arms, $outer, $inner, $geometry);
            $radius = $from + ($to - $from) * $progressInsideArm;

            $angle = -90 + ($index / $totalCells) * 360;
            $isSalida = $index % $cellsPerArm === 0;

            $cells[] = [
                'index' => $index,
                'arm' => $arm,
                'kind' => $isSalida ? 'salida' : 'normal',
                'safe' => false,
                'occupied' => $armsDetail[$arm]['occupied'],
                'next' => ($index + 1) % $totalCells,
                'x' => self::point($center, $angle, $radius)['x'],
                'y' => self::point($center, $angle, $radius)['y'],
            ];
        }

        $safeCells = [];
        foreach ($armsDetail as $arm) {
            if (! $arm['occupied']) {
                continue;
            }

            $safeCells[] = $arm['salidaIndex'];
            $safeCells[] = $arm['salidaIndex'] + intdiv($cellsPerArm, 2);
        }

        foreach ($cells as &$cell) {
            $cell['safe'] = in_array($cell['index'], $safeCells, true);
        }
        unset($cell);

        return [
            'arms' => $arms,
            'cellsPerArm' => $cellsPerArm,
            'homeLength' => $homeLength,
            'geometry' => $geometry,
            'viewBox' => '0 0 '.config('parchis.view_box').' '.config('parchis.view_box'),
            'center' => $center,
            'cellSize' => $cellSize,
            'palette' => $palette,
            'armsDetail' => $armsDetail,
            'bases' => $bases,
            'homes' => $homes,
            'cells' => $cells,
            'occupiedArms' => array_keys($colors),
            'safeCells' => $safeCells,
        ];
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @return array{x: float, y: float}
     */
    private static function offset(array $point, float $dx, float $dy): array
    {
        return [
            'x' => round($point['x'] + $dx, 2),
            'y' => round($point['y'] + $dy, 2),
        ];
    }

    /**
     * @param  array{x: int, y: int}  $center
     * @param  array{x: float, y: float}  $point
     */
    private static function distance(array $center, array $point): float
    {
        return sqrt(
            ($point['x'] - $center['x']) ** 2 + ($point['y'] - $center['y']) ** 2,
        );
    }

    private static function radiusForArm(int $arm, int $arms, float $outer, float $inner, string $geometry): float
    {
        if ($geometry === 'star') {
            return $arm % 2 === 0 ? $outer : $inner;
        }

        return $outer;
    }

    /**
     * @param  array{x: int, y: int}  $center
     * @return array{x: float, y: float}
     */
    private static function point(array $center, float $angleDegrees, float $radius): array
    {
        $angle = deg2rad($angleDegrees);

        return [
            'x' => round($center['x'] + $radius * cos($angle), 2),
            'y' => round($center['y'] + $radius * sin($angle), 2),
        ];
    }
}
