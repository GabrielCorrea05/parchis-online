<script setup lang="ts">
import { computed } from 'vue';
import CellShape from '@/components/CellShape.vue';
import PieceShape from '@/components/PieceShape.vue';
import { armWedge, rosettePoints } from '@/lib/boardDecor';
import { boardPlayerColor } from '@/lib/playerStyle';
import type { BoardConfig } from '@/types';

const props = defineProps<{
    board: BoardConfig;
}>();

const wedges = computed(() =>
    props.board.armsDetail.map((arm) => ({
        arm,
        points: armWedge(props.board.center, arm),
    })),
);
const rosette = computed(() => rosettePoints(props.board.center, 58));

const cellSize = computed(() => Math.max(12, props.board.cellSize));
const pieceRadius = computed(() => cellSize.value * 0.4);
const safeCells = computed(() => new Set<number>(props.board.safeCells ?? []));
</script>

<template>
    <svg
        :viewBox="board.viewBox"
        class="mx-auto h-auto w-full max-w-[420px]"
        role="img"
        :aria-label="`Previsualización del tablero con ${board.arms} brazos`"
    >
        <defs>
            <pattern
                id="preview-hatch-safe"
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
            <polygon
                v-for="wedge in wedges"
                :key="`wedge-${wedge.arm.index}`"
                :points="wedge.points"
                :fill="boardPlayerColor(wedge.arm.color)"
                :opacity="wedge.arm.occupied ? 0.16 : 0.06"
            />
            <polygon
                :points="rosette"
                fill="var(--board-center)"
                opacity="0.35"
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
            pattern-id="preview-hatch-safe"
        />

        <g
            v-for="arm in board.armsDetail"
            :key="`base-${arm.index}`"
            pointer-events="none"
        >
            <circle
                :cx="arm.base.x"
                :cy="arm.base.y"
                r="38"
                fill="var(--board-base)"
                :stroke="boardPlayerColor(arm.color)"
                :stroke-width="arm.occupied ? 3 : 1.5"
                :stroke-dasharray="arm.occupied ? undefined : '6 5'"
            />
            <circle
                :cx="arm.base.x"
                :cy="arm.base.y"
                r="20"
                fill="var(--board-cell)"
                :stroke="boardPlayerColor(arm.color)"
                stroke-width="2"
            />
            <PieceShape
                v-if="arm.occupied"
                :cx="arm.base.x"
                :cy="arm.base.y"
                :r="pieceRadius"
                :color="arm.color"
                :number="arm.index + 1"
                :interactive="false"
            />
        </g>

        <circle
            :cx="board.center.x"
            :cy="board.center.y"
            r="46"
            fill="var(--board-center)"
        />
        <text
            :x="board.center.x"
            :y="board.center.y - 2"
            text-anchor="middle"
            font-size="20"
            font-weight="700"
            fill="var(--board-on-cell)"
        >
            {{ board.arms }}
        </text>
        <text
            :x="board.center.x"
            :y="board.center.y + 18"
            text-anchor="middle"
            font-size="12"
            fill="var(--board-on-cell)"
            opacity="0.8"
        >
            brazos
        </text>
    </svg>
</template>
