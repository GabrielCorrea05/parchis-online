<?php

namespace App\Domain\Game\Services;

use App\Domain\Game\Exceptions\GameRuleViolation;

/**
 * Motor autoritativo del juego.
 *
 * Clase pura: recibe la configuración del tablero y un snapshot, y devuelve
 * snapshots nuevos + eventos de dominio. No depende de Laravel, HTTP ni Reverb.
 */
final class GameEngine
{
    private readonly MovementValidator $movement;

    private readonly CaptureResolver $captures;

    private readonly WinnerResolver $winner;

    private readonly int $goal;

    /**
     * @param  array{arms: int, cellsPerArm: int, homeLength: int, safeCells?: list<int>, startIndices?: list<int>}  $board
     */
    public function __construct(array $board)
    {
        $this->movement = new MovementValidator($board);
        $this->captures = new CaptureResolver($board);
        $this->goal = $this->movement->goal();
        $this->winner = new WinnerResolver($this->goal);
    }

    /**
     * @param  array<int, array{id: int, nickname: string, color: string, seat: int, isConnected?: bool}>  $players
     * @param  list<int>|null  $turnOrder  Si se entrega, se respeta; si no, empieza el host y sigue el seat.
     * @return array<string, mixed>
     */
    public static function initialState(
        array $players,
        int $hostPlayerId,
        ?array $turnOrder = null,
    ): array {
        $playersById = [];
        $seats = [];

        foreach ($players as $player) {
            $playersById[$player['id']] = [
                'id' => $player['id'],
                'nickname' => $player['nickname'],
                'color' => $player['color'],
                'seat' => $player['seat'],
                'isConnected' => $player['isConnected'] ?? true,
            ];
            $seats[$player['id']] = $player['seat'];
        }

        $order = $turnOrder;

        if ($order === null || $order === []) {
            $order = [$hostPlayerId];

            foreach ($players as $player) {
                if ($player['id'] !== $hostPlayerId) {
                    $order[] = $player['id'];
                }
            }

            usort($order, function (int $a, int $b) use ($hostPlayerId, $seats): int {
                if ($a === $hostPlayerId) {
                    return -1;
                }
                if ($b === $hostPlayerId) {
                    return 1;
                }

                return ($seats[$a] ?? 0) <=> ($seats[$b] ?? 0);
            });
        }

        $pieces = [];

        foreach ($players as $player) {
            for ($slot = 0; $slot < 4; $slot++) {
                $id = self::pieceId((int) $player['id'], $slot);
                $pieces[$id] = [
                    'id' => $id,
                    'playerId' => (int) $player['id'],
                    'seat' => (int) $player['seat'],
                    'slot' => $slot,
                    'progress' => null, // null = casa
                ];
            }
        }

        $captures = [];
        foreach ($players as $player) {
            $captures[(string) $player['id']] = 0;
        }

        return [
            'players' => $playersById,
            'pieces' => $pieces,
            'turnOrder' => $order,
            'currentTurnIndex' => 0,
            'currentPlayerId' => (int) $order[0],
            'consecutiveSixes' => 0,
            'pendingRoll' => null,
            'legalMoves' => [],
            'dice' => [
                'last' => null,
                'history' => [],
            ],
            'turns' => 0,
            'totalRolls' => 0,
            'captures' => $captures,
            'status' => 'in_progress',
            'winnerPlayerId' => null,
            'result' => null,
        ];
    }

    public static function pieceId(int $playerId, int $slot): string
    {
        return "{$playerId}-{$slot}";
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{state: array<string, mixed>, events: list<array<string, mixed>>}
     */
    public function roll(int $value, array $state): array
    {
        $this->assertPlayable($state);

        if ($state['pendingRoll'] !== null) {
            throw new GameRuleViolation('Mueve la ficha de la tirada anterior antes de volver a tirar.');
        }

        if ($value < 1 || $value > 6) {
            throw new GameRuleViolation('La tirada debe estar entre 1 y 6.');
        }

        $events = [];
        $playerId = (int) $state['currentPlayerId'];

        $state = $this->skipDisconnected($state);
        $playerId = (int) $state['currentPlayerId'];

        $consecutive = $value === 6 ? (int) $state['consecutiveSixes'] + 1 : 0;

        $state['dice']['last'] = $value;
        $state['dice']['history'][] = [
            'playerId' => $playerId,
            'value' => $value,
            'turn' => (int) $state['turns'] + 1,
            'consecutiveSixes' => $consecutive,
        ];
        $state['consecutiveSixes'] = $consecutive;
        $state['turns'] = (int) $state['turns'] + 1;
        $state['totalRolls'] = (int) $state['totalRolls'] + 1;

        $events[] = [
            'type' => 'dice_rolled',
            'playerId' => $playerId,
            'value' => $value,
            'consecutiveSixes' => $consecutive,
        ];

        // Tercer 6 consecutivo: se consume sin mover y se pasa el turno.
        if ($consecutive === 3) {
            $state['pendingRoll'] = null;
            $state['legalMoves'] = [];
            $events[] = [
                'type' => 'third_six',
                'playerId' => $playerId,
            ];

            return ['state' => $this->advanceTurn($state, $events), 'events' => $events];
        }

        $legal = $this->movement->legalMoves($value, $state['pieces'], $playerId);

        // Sin movimiento legal se pasa el turno, incluso si ha salido un 6.
        if ($legal === []) {
            $state['pendingRoll'] = null;
            $state['legalMoves'] = [];
            $events[] = [
                'type' => 'no_legal_move',
                'playerId' => $playerId,
                'value' => $value,
            ];

            return ['state' => $this->advanceTurn($state, $events), 'events' => $events];
        }

        $state['pendingRoll'] = $value;
        $state['legalMoves'] = $legal;

        return ['state' => $state, 'events' => $events];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{state: array<string, mixed>, events: list<array<string, mixed>>}
     */
    public function move(string $pieceId, array $state): array
    {
        $this->assertPlayable($state);

        if ($state['pendingRoll'] === null) {
            throw new GameRuleViolation('No hay ninguna tirada pendiente de mover.');
        }

        $roll = (int) $state['pendingRoll'];
        $playerId = (int) $state['currentPlayerId'];

        $this->movement->assertLegal($pieceId, $roll, $state['pieces'], $playerId);

        $events = [];
        $piece = $state['pieces'][$pieceId];
        $from = $piece['progress'];
        $to = $from === null ? 0 : (int) $from + $roll;

        $state['pieces'][$pieceId]['progress'] = $to;

        if ($from === null) {
            $events[] = [
                'type' => 'piece_out',
                'pieceId' => $pieceId,
                'playerId' => $playerId,
                'cell' => $this->movement->ringCellFor((int) $piece['seat'], $to),
            ];
        } else {
            $events[] = [
                'type' => 'piece_moved',
                'pieceId' => $pieceId,
                'playerId' => $playerId,
                'from' => (int) $from,
                'to' => $to,
                'cell' => $this->movement->ringCellFor((int) $piece['seat'], $to),
            ];
        }

        $captured = $this->captures->resolve(
            $state['pieces'],
            $playerId,
            (int) $piece['seat'],
            $to,
            $from,
        );

        if ($captured !== []) {
            $state['captures'][(string) $playerId] = (int) ($state['captures'][(string) $playerId] ?? 0) + count($captured);
            $events[] = [
                'type' => 'capture',
                'playerId' => $playerId,
                'captured' => $captured,
            ];
        }

        $state['pendingRoll'] = null;
        $state['legalMoves'] = [];

        if ($this->hasWon($state['pieces'], $playerId)) {
            $state['status'] = 'finished';
            $state['winnerPlayerId'] = $playerId;
            $state['result'] = $this->makeResult($state, $playerId);
            $events[] = [
                'type' => 'game_finished',
                'playerId' => $playerId,
                'result' => $state['result'],
            ];

            return ['state' => $state, 'events' => $events];
        }

        // Victoria antes que turno extra: si llegamos aquí, el 6 da otra tirada.
        if ($roll === 6) {
            $events[] = [
                'type' => 'extra_turn',
                'playerId' => $playerId,
            ];

            return ['state' => $state, 'events' => $events];
        }

        return ['state' => $this->advanceTurn($state, $events), 'events' => $events];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array{playerId: int, nickname: string, color: string, seat: int, pieces_meta: int, progress_total: int, captures: int}>
     */
    public function ranking(array $state): array
    {
        $ranking = $this->winner->rank($state['pieces'], $state['players']);

        foreach ($ranking as &$entry) {
            $entry['captures'] = (int) ($state['captures'][(string) $entry['playerId']] ?? 0);
        }
        unset($entry);

        return $ranking;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function assertPlayable(array $state): void
    {
        if (($state['status'] ?? null) !== 'in_progress') {
            throw new GameRuleViolation('La partida no está en curso.');
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function skipDisconnected(array $state): array
    {
        $order = $state['turnOrder'];
        $total = count($order);

        for ($checked = 0; $checked < $total; $checked++) {
            $playerId = (int) $state['currentPlayerId'];

            if (($state['players'][$playerId]['isConnected'] ?? true) === true) {
                break;
            }

            $state['consecutiveSixes'] = 0;
            $this->moveIndex($state);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function advanceTurn(array &$state, array &$events): array
    {
        $previousId = (int) $state['currentPlayerId'];
        $order = $state['turnOrder'];
        $total = count($order);

        for ($checked = 0; $checked < $total; $checked++) {
            $this->moveIndex($state);
            $candidate = (int) $state['currentPlayerId'];

            if (($state['players'][$candidate]['isConnected'] ?? true) === true) {
                break;
            }
        }

        $state['consecutiveSixes'] = 0;

        if ((int) $state['currentPlayerId'] !== $previousId) {
            $events[] = [
                'type' => 'turn_advanced',
                'fromPlayerId' => $previousId,
                'playerId' => (int) $state['currentPlayerId'],
            ];
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function moveIndex(array &$state): void
    {
        $total = count($state['turnOrder']);

        if ($total === 0) {
            return;
        }

        $state['currentTurnIndex'] = ((int) $state['currentTurnIndex'] + 1) % $total;
        $state['currentPlayerId'] = (int) $state['turnOrder'][$state['currentTurnIndex']];
    }

    /**
     * @param  array<string, array{playerId: int, progress: int|null}>  $pieces
     */
    private function hasWon(array $pieces, int $playerId): bool
    {
        $inGoal = 0;

        foreach ($pieces as $piece) {
            if ($piece['playerId'] === $playerId
                && $piece['progress'] !== null
                && $piece['progress'] >= $this->goal) {
                $inGoal++;
            }
        }

        return $inGoal === 4;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function makeResult(array $state, int $winnerPlayerId): array
    {
        $ranking = $this->ranking($state);
        $totalCaptures = 0;

        foreach ($state['captures'] as $count) {
            $totalCaptures += (int) $count;
        }

        return [
            'winnerPlayerId' => $winnerPlayerId,
            'ranking' => $ranking,
            'turns' => (int) $state['turns'],
            'total_rolls' => (int) $state['totalRolls'],
            'total_captures' => $totalCaptures,
            'captures_by_player' => $state['captures'],
        ];
    }
}
