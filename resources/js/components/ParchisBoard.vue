<script setup lang="ts">
import { computed, ref } from 'vue';
import CellShape from '@/components/CellShape.vue';
import PieceShape from '@/components/PieceShape.vue';
import { armWedge, rosettePoints } from '@/lib/boardDecor';
import { boardPlayerColor } from '@/lib/playerStyle';
import type { GameBoard, GameBoardHome, GamePiece, Point } from '@/types';

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
        const start = props.board.startIndices?.[piece.seat] ?? 0;
        const cell = (start + piece.progress) % ringCells.value;
        const list = map.get(cell) ?? [];
        list.push(piece);
        map.set(cell, list);
    }
    return map;
});

const playerById = computed(() => new Map(props.players.map((p) => [p.id, p])));

const wedges = computed(() =>
    props.board.armsDetail.map((arm) => ({
        arm,
        points: armWedge(props.board.center, arm),
    })),
);
const rosette = computed(() =>
    rosettePoints(props.board.center, Math.max(42, cellSize.value * 1.8)),
);

function baseCircle(slots: Point[]): { cx: number; cy: number; r: number } {
    const xs = slots.map((slot) => slot.x);
    const ys = slots.map((slot) => slot.y);
    const pad = cellSize.value * 0.62;

    return {
        cx: (Math.min(...xs) + Math.max(...xs)) / 2,
        cy: (Math.min(...ys) + Math.max(...ys)) / 2,
        r:
            Math.max(
                Math.max(...xs) - Math.min(...xs),
                Math.max(...ys) - Math.min(...ys),
            ) /
                2 +
            pad,
    };
}

function cellIndex(piece: GamePiece): number {
    const start = props.board.startIndices?.[piece.seat] ?? 0;
    return (start + piece.progress!) % ringCells.value;
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

/** Triángulo de meta apuntando al centro, derivado del ángulo del brazo. */
function goalTriangle(home: GameBoardHome, goal: Point): string {
    const angle = props.board.armsDetail[home.arm]?.angle ?? -90;
    const radians = (angle * Math.PI) / 180;
    const dx = Math.cos(radians);
    const dy = Math.sin(radians);
    const px = -dy;
    const py = dx;
    const size = cellSize.value * 0.62;

    const apex = {
        x: goal.x - dx * size * 0.62,
        y: goal.y - dy * size * 0.62,
    };
    const left = {
        x: goal.x + dx * size * 0.38 + px * size * 0.55,
        y: goal.y + dy * size * 0.38 + py * size * 0.55,
    };
    const right = {
        x: goal.x + dx * size * 0.38 - px * size * 0.55,
        y: goal.y + dy * size * 0.38 - py * size * 0.55,
    };

    return [apex, left, right]
        .map((point) => `${point.x.toFixed(1)},${point.y.toFixed(1)}`)
        .join(' ');
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
    const cell = props.board.cells[cellIndex(piece)];
    return cell ? { x: cell.x, y: cell.y } : null;
}

function offsetFor(piece: GamePiece): { x: number; y: number } {
    if (piece.progress === null || piece.progress >= ringCells.value) {
        return { x: 0, y: 0 };
    }
    const index = cellIndex(piece);
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

        <!-- Marco de madera y fieltro: un solo tablero físico, sin imágenes. -->
        <g aria-hidden="true" pointer-events="none">
            <rect
                x="2"
                y="2"
                width="796"
                height="796"
                rx="34"
                fill="var(--board-frame-dark)"
            />
            <rect
                x="9"
                y="9"
                width="782"
                height="782"
                rx="29"
                fill="var(--board-frame-light)"
            />
            <rect
                x="18"
                y="18"
                width="764"
                height="764"
                rx="24"
                fill="var(--board-bg)"
            />
            <rect
                x="30"
                y="30"
                width="740"
                height="740"
                rx="18"
                fill="var(--board-felt)"
                stroke="var(--board-frame-dark)"
                stroke-width="2"
            />
            <rect
                x="37"
                y="37"
                width="726"
                height="726"
                rx="14"
                fill="none"
                stroke="var(--board-center)"
                stroke-width="1.5"
                opacity="0.7"
            />
            <circle
                v-for="corner in [
                    [68, 68],
                    [732, 68],
                    [68, 732],
                    [732, 732],
                ]"
                :key="`corner-${corner[0]}-${corner[1]}`"
                :cx="corner[0]"
                :cy="corner[1]"
                r="7"
                fill="var(--board-center)"
                opacity="0.8"
            />
        </g>

        <!-- Cuñas de color impresas en cada brazo. -->
        <g aria-hidden="true" pointer-events="none">
            <polygon
                v-for="wedge in wedges"
                :key="`wedge-${wedge.arm.index}`"
                :points="wedge.points"
                :fill="boardPlayerColor(wedge.arm.color)"
                :opacity="wedge.arm.occupied ? 0.16 : 0.06"
            />
            <circle
                :cx="board.center.x"
                :cy="board.center.y"
                r="74"
                fill="var(--board-center)"
                opacity="0.2"
            />
            <polygon
                :points="rosette"
                fill="var(--board-center)"
                opacity="0.35"
            />
        </g>

        <g v-for="base in board.bases" :key="`base-${base.arm}`">
            <circle
                v-bind="baseCircle(base.slots)"
                fill="var(--board-base)"
                :stroke="boardPlayerColor(base.color)"
                :stroke-width="base.occupied ? 3 : 1.5"
                :stroke-dasharray="base.occupied ? undefined : '6 5'"
                opacity="0.95"
            />
            <circle
                v-for="(slot, i) in base.slots"
                :key="i"
                :cx="slot.x"
                :cy="slot.y"
                :r="cellSize * 0.46"
                fill="var(--board-cell)"
                :stroke="boardPlayerColor(base.color)"
                stroke-width="2"
            />
            <circle
                v-for="(slot, i) in base.slots"
                :key="`hole-${i}`"
                :cx="slot.x"
                :cy="slot.y"
                :r="cellSize * 0.3"
                fill="var(--board-frame-dark)"
                opacity="0.16"
                pointer-events="none"
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
                :fill="boardPlayerColor(home.color)"
                :opacity="home.occupied ? (i % 2 === 0 ? 0.34 : 0.2) : 0.1"
                stroke="var(--board-line)"
            />
            <polygon
                v-for="(goal, i) in home.goals"
                :key="`goal-${i}`"
                :points="goalTriangle(home, goal)"
                fill="var(--board-cell)"
                :stroke="boardPlayerColor(home.color)"
                stroke-width="2.5"
                stroke-linejoin="round"
            />
        </g>

        <CellShape
            v-for="cell in board.cells"
            :key="cell.index"
            :x="cell.x"
            :y="cell.y"
            :size="cellSize"
            :kind="cell.kind"
            :color="
                cell.occupied ? (board.armsDetail[cell.arm]?.color ?? '') : ''
            "
            :safe="safeCells.has(cell.index)"
            :label="cell.label"
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
