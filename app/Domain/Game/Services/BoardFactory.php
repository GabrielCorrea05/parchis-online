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
            $players >= 2 && $players <= 4 => 4,
            $players >= 5 && $players <= 6 => 6,
            default => throw new InvalidArgumentException("Número de jugadores no soportado: {$players}"),
        };
    }

    /**
     * Colores canónicos de salida: amarillo=5, azul=22, rojo=39, verde=56
     * (y cian/violeta en los brazos quinto y sexto del tablero de estrella).
     *
     * @return list<string>
     */
    public static function colorOrder(int $arms): array
    {
        if (! in_array($arms, [4, 6], true)) {
            throw new InvalidArgumentException("Número de brazos no soportado: {$arms}");
        }

        $order = config('parchis.color_order');

        if (! is_array($order)) {
            throw new InvalidArgumentException('Falta parchis.color_order en la configuración.');
        }

        return array_values(array_slice($order, 0, $arms));
    }

    /**
     * Asigna asientos de forma determinista según el color elegido.
     * Si un color no existe en este tablero, se le da el primer asiento libre.
     *
     * @param  iterable<string>  $colors  Colores en orden de llegada.
     * @return array<int, array{seat: int, color: string}> Índice de llegada => asiento y color final.
     */
    public static function seatAssignments(iterable $colors, int $arms): array
    {
        $order = self::colorOrder($arms);
        $used = [];
        $assignments = [];

        foreach ($colors as $position => $color) {
            $color = (string) $color;
            $preferred = array_search($color, $order, true);

            if ($preferred !== false && ! isset($used[$preferred])) {
                $seat = $preferred;
            } else {
                $free = array_values(array_diff(range(0, $arms - 1), array_keys($used)));

                if ($free === []) {
                    throw new InvalidArgumentException('No quedan asientos libres en el tablero.');
                }

                $seat = $free[0];
            }

            $used[$seat] = true;
            $assignments[$position] = [
                'seat' => $seat,
                'color' => $order[$seat],
            ];
        }

        return $assignments;
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
        $cellSize = (int) ($boards[$arms]['cell_size'] ?? config('parchis.cell_size'));
        $homeLength = (int) ($boards[$arms]['home_length'] ?? config('parchis.home_length'));
        $safeOffsets = $boards[$arms]['safe_offsets'] ?? [0, intdiv($cellsPerArm, 2)];
        $labelOffset = (int) ($boards[$arms]['label_offset'] ?? 0);
        $crossOffsets = $boards[$arms]['cross_arm_offsets'] ?? [];
        $gridStep = (float) config('parchis.view_box') / 21;
        $palette = config('parchis.colors');
        $colorOrder = self::colorOrder($arms);
        $allArmsSafe = (bool) ($boards[$arms]['all_arms_safe'] ?? false);

        $armsDetail = [];
        for ($arm = 0; $arm < $arms; $arm++) {
            $angle = self::angleForArm($arm, $arms, $geometry);
            $color = $colors[$arm] ?? $colorOrder[$arm];
            $base = $geometry === 'cross'
                ? self::gridPoint($center, self::rotateOffset([-9, 9], $arm), $gridStep)
                : self::point($center, $angle, $outer + 70);
            $entrance = $geometry === 'cross' && isset($crossOffsets[0])
                ? self::gridPoint($center, self::rotateOffset($crossOffsets[0], $arm), $gridStep)
                : self::point($center, $angle, self::radiusForArm($arm, $arms, $outer, $inner, $geometry));

            $armsDetail[] = [
                'index' => $arm,
                'angle' => $angle,
                'color' => $color,
                'hex' => $palette[$color] ?? '#64748b',
                'occupied' => array_key_exists($arm, $colors),
                'salidaIndex' => $arm * $cellsPerArm,
                'base' => $base,
                'entrance' => $entrance,
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

            $homeCells = [];
            if ($geometry === 'cross') {
                // La casilla 4 está en (1,+7) y la 5 en (+2,+6): el pasillo
                // arranca en (0,+7) y sube por la columna central hasta (0,+1).
                for ($step = 0; $step < $homeLength; $step++) {
                    $homeCells[] = self::gridPoint(
                        $center,
                        self::rotateOffset([0, 7 - $step], (int) $arm['index']),
                        $gridStep,
                    );
                }
            } else {
                $entranceRadius = self::distance($center, $arm['entrance']);
                for ($step = 0; $step < $homeLength; $step++) {
                    $radius = max(62.0, $entranceRadius - 26 - $step * (($entranceRadius - 100) / max(1, $homeLength)));
                    $homeCells[] = self::point($center, $angle, $radius);
                }
            }

            $goals = [];
            if ($geometry === 'cross') {
                // Cuatro plazas en torno al centro, una por brazo, girando
                // la misma plantilla como el resto del tablero.
                $goalOffset = self::rotateOffset([0.45, 0.45], (int) $arm['index']);
                $goals[] = self::gridPoint($center, $goalOffset, $gridStep);
            } else {
                for ($slot = 0; $slot < 4; $slot++) {
                    $goalAngle = $angle - 18 + ($slot * 12);
                    $goals[] = self::point($center, $goalAngle, 42);
                }
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

        $occupiedArms = array_map('intval', array_keys($colors));
        sort($occupiedArms);

        $cells = [];
        for ($index = 0; $index < $totalCells; $index++) {
            $arm = intdiv($index, $cellsPerArm);
            $offsetInArm = $index % $cellsPerArm;

            if ($geometry === 'cross' && isset($crossOffsets[$offsetInArm])) {
                $point = self::gridPoint(
                    $center,
                    self::rotateOffset($crossOffsets[$offsetInArm], $arm),
                    $gridStep,
                );
            } else {
                $progressInsideArm = $offsetInArm / $cellsPerArm;
                $from = self::radiusForArm($arm, $arms, $outer, $inner, $geometry);
                $to = self::radiusForArm(($arm + 1) % $arms, $arms, $outer, $inner, $geometry);
                $radius = $from + ($to - $from) * $progressInsideArm;
                $angle = -90 + ($index / $totalCells) * 360;
                $point = self::point($center, $angle, $radius);
            }

            $cells[] = [
                'index' => $index,
                'arm' => $arm,
                'kind' => $offsetInArm === 0 ? 'salida' : 'normal',
                'safe' => false,
                'occupied' => $armsDetail[$arm]['occupied'],
                'next' => ($index + 1) % $totalCells,
                'label' => (($index + $labelOffset) % $totalCells) + 1,
                'x' => $point['x'],
                'y' => $point['y'],
            ];
        }

        $safeCells = [];
        foreach ($armsDetail as $arm) {
            // En la cruz clásica las 12 seguras existen aunque el brazo esté
            // vacío: son una propiedad del tablero, no del jugador.
            if (! $allArmsSafe && ! $arm['occupied']) {
                continue;
            }

            foreach ($safeOffsets as $offset) {
                $safeCells[] = (int) $arm['salidaIndex'] + (int) $offset;
            }
        }
        sort($safeCells);

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
            'occupiedArms' => $occupiedArms,
            'safeCells' => $safeCells,
            'startIndices' => array_map('intval', array_column($armsDetail, 'salidaIndex')),
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
     * Gira una celda de la plantilla del brazo 0 tantos cuartos de vuelta
     * como indique el brazo (sentido antihorario, como el tablero clásico).
     *
     * @param  array<int, float|int>  $offset
     * @return array<int, float|int>
     */
    private static function rotateOffset(array $offset, int $arm): array
    {
        [$x, $y] = $offset;

        for ($i = 0; $i < $arm; $i++) {
            [$x, $y] = [$y, -$x];
        }

        return [$x, $y];
    }

    /**
     * @param  array{x: int, y: int}  $center
     * @param  array<int, float|int>  $offset
     * @return array{x: float, y: float}
     */
    private static function gridPoint(array $center, array $offset, float $step): array
    {
        return [
            'x' => round($center['x'] + $offset[0] * $step, 2),
            'y' => round($center['y'] + $offset[1] * $step, 2),
        ];
    }

    private static function angleForArm(int $arm, int $arms, string $geometry): float
    {
        if ($geometry === 'cross') {
            return 90 - ($arm * 360 / $arms);
        }

        return -90 + ($arm * 360 / $arms);
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
