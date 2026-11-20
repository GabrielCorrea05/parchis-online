<script setup lang="ts">
import { computed } from 'vue';
import CellShape from '@/components/CellShape.vue';
import PieceShape from '@/components/PieceShape.vue';
import type { BoardConfig } from '@/types';

const props = defineProps<{
    board: BoardConfig;
}>();

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

        <circle
            :cx="board.center.x"
            :cy="board.center.y"
            r="310"
            fill="none"
            stroke="var(--board-line)"
            stroke-width="1"
            stroke-dasharray="6 8"
        />

        <CellShape
            v-for="cell in board.cells"
            :key="cell.index"
            :x="cell.x"
            :y="cell.y"
            :size="cellSize"
            :kind="cell.kind"
            :color="board.armsDetail[cell.arm]?.color ?? ''"
            :safe="safeCells.has(cell.index)"
            pattern-id="preview-hatch-safe"
        />

        <g
            v-for="arm in board.armsDetail"
            :key="`base-${arm.index}`"
            pointer-events="none"
        >
            <rect
                :x="arm.base.x - 24"
                :y="arm.base.y - 24"
                width="48"
                height="48"
                rx="10"
                fill="var(--board-base)"
                :stroke="
                    arm.occupied
                        ? `var(--player-${arm.color})`
                        : 'var(--board-line)'
                "
                :stroke-width="arm.occupied ? 2.5 : 1"
                :stroke-dasharray="arm.occupied ? undefined : '4 4'"
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
            fill="var(--c-ink)"
        />
        <text
            :x="board.center.x"
            :y="board.center.y - 2"
            text-anchor="middle"
            font-size="20"
            font-weight="700"
            fill="var(--c-surface)"
        >
            {{ board.arms }}
        </text>
        <text
            :x="board.center.x"
            :y="board.center.y + 18"
            text-anchor="middle"
            font-size="12"
            fill="var(--c-surface)"
            opacity="0.75"
        >
            brazos
        </text>
    </svg>
</template>
