<?php

namespace App\Domain\Game\Services;

use InvalidArgumentException;

/**
 * Única fuente de verdad para la geometría del tablero.
 *
 * Solo existe el parchís español clásico: cruz de 4 brazos, 68 casillas,
 * casa circular de 8x8 en cada esquina y 7 casillas de pasillo por color.
 * El cliente recibe este array y solo lo dibuja.
 */
final class BoardFactory
{
    private const GRID = 19;

    private const SAFE_LABELS = [5, 12, 17, 22, 29, 34, 39, 46, 51, 56, 63, 68];

    public static function armsForPlayers(int $players): int
    {
        if ($players < 2 || $players > 4) {
            throw new InvalidArgumentException("Número de jugadores no soportado: {$players}");
        }

        return 4;
    }

    /**
     * Colores canónicos: amarillo=5, azul=22, rojo=39 y verde=56.
     *
     * @return list<string>
     */
    public static function colorOrder(int $arms): array
    {
        if ($arms !== 4) {
            throw new InvalidArgumentException("Número de brazos no soportado: {$arms}");
        }

        $order = config('parchis.color_order');

        if (! is_array($order)) {
            throw new InvalidArgumentException('Falta parchis.color_order en la configuración.');
        }

        return array_values($order);
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
        if ($arms !== 4) {
            throw new InvalidArgumentException("Número de brazos no soportado: {$arms}");
        }

        $board = config('parchis.boards.4');

        if (! is_array($board)) {
            throw new InvalidArgumentException('Falta la configuración del tablero de 4 brazos.');
        }

        $cellsPerArm = (int) $board['cells_per_arm'];
        $totalCells = $arms * $cellsPerArm;
        $homeLength = (int) $board['home_length'];
        $labelOffset = (int) $board['label_offset'];
        $step = (float) config('parchis.view_box') / self::GRID;
        $center = config('parchis.center');
        $palette = config('parchis.colors');
        $colorOrder = self::colorOrder($arms);

        $safeCells = array_map(
            fn (int $label): int => self::indexForLabel($label, $totalCells, $labelOffset),
            self::SAFE_LABELS,
        );
        sort($safeCells);

        $armsDetail = [];
        for ($arm = 0; $arm < $arms; $arm++) {
            $angle = 90 - ($arm * 360 / $arms);
            $color = $colors[$arm] ?? $colorOrder[$arm];
            $base = self::gridPoint($center, self::rotateOffset([5, 5], $arm), $step);
            $entrance = self::gridPoint($center, self::rotateOffset([0, 8], $arm), $step);

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
            $armIndex = (int) $arm['index'];
            $base = $arm['base'];
            $slots = [];

            foreach ([-1.35, 1.35] as $dx) {
                foreach ([-1.35, 1.35] as $dy) {
                    $slots[] = self::offset($base, $dx * $step, $dy * $step);
                }
            }

            $bases[] = [
                'arm' => $armIndex,
                'color' => $arm['color'],
                'hex' => $arm['hex'],
                'occupied' => $arm['occupied'],
                'slots' => $slots,
            ];

            $homeCells = [];
            for ($cell = 0; $cell < $homeLength; $cell++) {
                $homeCells[] = self::gridPoint(
                    $center,
                    self::rotateOffset([0, 8 - $cell], $armIndex),
                    $step,
                );
            }

            $goalOffsets = [
                [-0.62, 0.35],
                [0.62, 0.35],
                [-0.28, 1.02],
                [0.28, 1.02],
            ];
            $goals = array_map(
                fn (array $offset): array => self::gridPoint(
                    $center,
                    self::rotateOffset($offset, $armIndex),
                    $step,
                ),
                $goalOffsets,
            );

            $homes[] = [
                'arm' => $armIndex,
                'color' => $arm['color'],
                'hex' => $arm['hex'],
                'occupied' => $arm['occupied'],
                'cells' => $homeCells,
                'goals' => $goals,
            ];
        }

        $cells = [];
        for ($index = 0; $index < $totalCells; $index++) {
            $label = (($index + $labelOffset) % $totalCells) + 1;
            [$row, $column] = self::gridCellForLabel($label);
            $point = self::gridPoint($center, [$column - 9, $row - 9], $step);
            $arm = intdiv($index, $cellsPerArm);

            $cells[] = [
                'index' => $index,
                'arm' => $arm,
                'kind' => $index % $cellsPerArm === 0 ? 'salida' : 'normal',
                'safe' => in_array($index, $safeCells, true),
                'occupied' => $armsDetail[$arm]['occupied'],
                'next' => ($index + 1) % $totalCells,
                'label' => $label,
                'x' => $point['x'],
                'y' => $point['y'],
                'row' => $row,
                'column' => $column,
            ];
        }

        $occupiedArms = array_map('intval', array_keys($colors));
        sort($occupiedArms);

        return [
            'arms' => $arms,
            'cellsPerArm' => $cellsPerArm,
            'homeLength' => $homeLength,
            'geometry' => 'cross',
            'viewBox' => '0 0 '.config('parchis.view_box').' '.config('parchis.view_box'),
            'center' => $center,
            'cellSize' => (int) $board['cell_size'],
            'gridSize' => self::GRID,
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

    private static function indexForLabel(int $label, int $total, int $offset): int
    {
        return (($label - 1 - $offset) % $total + $total) % $total;
    }

    /**
     * Devuelve (fila, columna) en la rejilla 19x19 para una casilla del
     * recorrido exterior. Es la única definición del camino clásico.
     *
     * @return array{0: int, 1: int}
     */
    private static function gridCellForLabel(int $label): array
    {
        if ($label >= 1 && $label <= 8) {
            return [19 - $label, 10];
        }

        if ($label >= 9 && $label <= 15) {
            return [10, $label + 2];
        }

        if ($label >= 16 && $label <= 18) {
            return [26 - $label, 18];
        }

        if ($label >= 19 && $label <= 25) {
            return [8, 36 - $label];
        }

        if ($label >= 26 && $label <= 32) {
            return [33 - $label, 10];
        }

        if ($label >= 33 && $label <= 35) {
            return [0, 43 - $label];
        }

        if ($label >= 36 && $label <= 42) {
            return [$label - 35, 8];
        }

        if ($label >= 43 && $label <= 49) {
            return [8, 50 - $label];
        }

        if ($label >= 50 && $label <= 52) {
            return [$label - 42, 0];
        }

        if ($label >= 53 && $label <= 59) {
            return [10, $label - 52];
        }

        if ($label >= 60 && $label <= 66) {
            return [$label - 49, 8];
        }

        if ($label === 67) {
            return [18, 8];
        }

        if ($label === 68) {
            return [18, 9];
        }

        throw new InvalidArgumentException("Casilla de recorrido no válida: {$label}");
    }

    /**
     * @param  array{x: float|int, y: float|int}  $point
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
     * @param  array{x: float|int, y: float|int}  $center
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
}
