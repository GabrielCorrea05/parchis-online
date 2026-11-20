<script setup lang="ts">
import { computed, ref } from 'vue';
import CellShape from '@/components/CellShape.vue';
import PieceShape from '@/components/PieceShape.vue';
import { playerColor } from '@/lib/playerStyle';
import type { GameBoard, GamePiece } from '@/types';

const props = withDefaults(
    defineProps<{
        board: GameBoard;
        pieces: GamePiece[];
        legalMoves?: string[];
        players?: { id: number; nickname: string; color: string }[];
        pieceLabel?: (piece: GamePiece) => string;
        descriptionId?: string;
    }>(),
    {
        legalMoves: () => [],
        players: () => [],
        pieceLabel: undefined,
        descriptionId: undefined,
    },
);

const emit = defineEmits<{
    move: [pieceId: string];
}>();

const svgRef = ref<SVGSVGElement | null>(null);
const cellSize = computed(() => Math.max(20, props.board.cellSize ?? 32));
const pieceRadius = computed(() => cellSize.value * 0.36);
const safeCells = computed(() => new Set<number>(props.board.safeCells ?? []));
const ringCells = computed(() => props.board.arms * props.board.cellsPerArm);
const totalPath = computed(() => ringCells.value + props.board.homeLength);

const occupied = computed(() => {
    const map = new Map<number, GamePiece[]>();
    for (const piece of props.pieces) {
        if (piece.progress === null || piece.progress >= ringCells.value) {
            continue;
        }
        const index =
            props.board.cells.find(
                (cell) => cell.arm === piece.seat && cell.kind === 'salida',
            )?.index ?? 0;
        const cell = (index + piece.progress) % ringCells.value;
        const list = map.get(cell) ?? [];
        list.push(piece);
        map.set(cell, list);
    }
    return map;
});

const playerById = computed(() => new Map(props.players.map((p) => [p.id, p])));

function cellIndex(piece: GamePiece): number | null {
    const cell = props.board.cells.find(
        (item) => item.arm === piece.seat && item.kind === 'salida',
    );
    return cell ? (cell.index + piece.progress!) % ringCells.value : null;
}

function homePoint(piece: GamePiece): { x: number; y: number } | null {
    const home = props.board.homes[piece.seat];
    const column = piece.progress! - props.board.cellsPerArm * props.board.arms;
    return home?.cells[column] ?? null;
}

function goalPoint(piece: GamePiece): { x: number; y: number } | null {
    const slot = piece.slot - 1;
    return props.board.homes[piece.seat]?.goals[slot] ?? null;
}

function basePoint(piece: GamePiece): { x: number; y: number } | null {
    const slot = Math.max(0, Math.min(3, piece.slot - 1));
    return props.board.bases[piece.seat]?.slots[slot] ?? null;
}

function piecePoint(piece: GamePiece): { x: number; y: number } | null {
    if (piece.progress === null) {
        return basePoint(piece);
    }
    if (piece.progress >= totalPath.value) {
        return goalPoint(piece);
    }
    if (piece.progress >= ringCells.value) {
        return homePoint(piece);
    }
    const index = cellIndex(piece);
    const cell = index === null ? undefined : props.board.cells[index];
    return cell ? { x: cell.x, y: cell.y } : null;
}

function offsetFor(piece: GamePiece): { x: number; y: number } {
    if (piece.progress === null || piece.progress >= ringCells.value) {
        return { x: 0, y: 0 };
    }
    const index = cellIndex(piece) ?? -1;
    const list = occupied.value.get(index) ?? [piece];
    const i = list.findIndex((item) => item.id === piece.id);
    const offset = (i < 0 ? 0 : i) - (list.length - 1) / 2;
    return {
        x: offset * cellSize.value * 0.24,
        y: offset * cellSize.value * 0.1,
    };
}

function onKeydown(event: KeyboardEvent): void {
    if (
        !['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'].includes(event.key)
    ) {
        return;
    }

    const targets = Array.from(
        svgRef.value?.querySelectorAll<SVGGElement>(
            '[data-legal-piece="true"]',
        ) ?? [],
    );

    if (targets.length === 0) {
        return;
    }

    event.preventDefault();
    const current = targets.indexOf(document.activeElement as SVGGElement);
    const delta =
        event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1;
    const next =
        current < 0
            ? delta > 0
                ? 0
                : targets.length - 1
            : (current + delta + targets.length) % targets.length;
    targets[next]?.focus();
}
</script>

<template>
    <svg
        ref="svgRef"
        :viewBox="board.viewBox"
        class="board-svg"
        role="group"
        :aria-label="`Tablero de parchís con ${board.arms} brazos. Usa Tab o las flechas para recorrer las fichas movibles y Intro o Espacio para moverlas.`"
        :aria-describedby="descriptionId"
        @keydown="onKeydown"
    >
        <defs>
            <pattern
                id="hatch-safe"
                width="6"
                height="6"
                patternUnits="userSpaceOnUse"
                patternTransform="rotate(45)"
            >
                <rect width="6" height="6" fill="var(--board-cell)" />
                <line
                    x1="0"
                    y1="0"
                    x2="0"
                    y2="6"
                    stroke="var(--board-safe-line)"
                    stroke-width="2"
                />
            </pattern>
        </defs>

        <rect
            x="0"
            y="0"
            width="800"
            height="800"
            rx="32"
            fill="var(--board-bg)"
            stroke="var(--board-line)"
            stroke-width="2"
        />

        <g v-for="base in board.bases" :key="`base-${base.arm}`">
            <rect
                v-for="(slot, i) in base.slots"
                :key="i"
                :x="slot.x - cellSize * 0.55"
                :y="slot.y - cellSize * 0.55"
                :width="cellSize * 1.1"
                :height="cellSize * 1.1"
                rx="6"
                fill="var(--board-base)"
                :stroke="playerColor(base.color)"
                :stroke-width="base.occupied ? 2.5 : 1"
                :stroke-dasharray="base.occupied ? undefined : '4 4'"
            />
        </g>

        <g v-for="home in board.homes" :key="`home-${home.arm}`">
            <rect
                v-for="(cell, i) in home.cells"
                :key="`cell-${i}`"
                :x="cell.x - cellSize * 0.42"
                :y="cell.y - cellSize * 0.42"
                :width="cellSize * 0.84"
                :height="cellSize * 0.84"
                rx="4"
                :fill="playerColor(home.color)"
                :opacity="home.occupied ? 0.2 : 0.06"
                stroke="var(--board-line)"
            />
            <rect
                v-for="(goal, i) in home.goals"
                :key="`goal-${i}`"
                :x="goal.x - cellSize * 0.42"
                :y="goal.y - cellSize * 0.42"
                :width="cellSize * 0.84"
                :height="cellSize * 0.84"
                rx="4"
                fill="var(--board-cell)"
                :stroke="playerColor(home.color)"
                stroke-width="2"
            />
        </g>

        <CellShape
            v-for="cell in board.cells"
            :key="cell.index"
            :x="cell.x"
            :y="cell.y"
            :size="cellSize"
            :kind="cell.kind"
            :color="board.armsDetail[cell.arm]?.color ?? ''"
            :safe="safeCells.has(cell.index)"
        />

        <g v-for="piece in pieces" :key="piece.id">
            <PieceShape
                v-if="piecePoint(piece)"
                :cx="piecePoint(piece)!.x + offsetFor(piece).x"
                :cy="piecePoint(piece)!.y + offsetFor(piece).y"
                :r="pieceRadius"
                :color="playerById.get(piece.playerId)?.color ?? 'red'"
                :number="piece.slot"
                :legal="legalMoves.includes(piece.id)"
                :label="pieceLabel ? pieceLabel(piece) : undefined"
                @select="emit('move', piece.id)"
            />
        </g>
    </svg>
</template>
