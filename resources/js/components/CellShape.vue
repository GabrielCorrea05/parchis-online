<script setup lang="ts">
import { computed } from 'vue';
import { playerColor } from '@/lib/playerStyle';

const props = withDefaults(
    defineProps<{
        x: number;
        y: number;
        size: number;
        kind?: string;
        color?: string;
        safe?: boolean;
        patternId?: string;
    }>(),
    {
        kind: 'normal',
        color: '',
        safe: false,
        patternId: 'hatch-safe',
    },
);

const fill = computed(() => {
    if (props.kind === 'salida' && props.color) {
        return playerColor(props.color);
    }

    return 'var(--board-cell)';
});

const stroke = computed(() => {
    if (props.kind === 'salida' && props.color) {
        return playerColor(props.color);
    }
    if (props.kind === 'meta' || props.kind === 'goal') {
        return 'var(--c-accent)';
    }

    return 'var(--board-line)';
});

const pattern = computed(() =>
    props.safe ? `url(#${props.patternId})` : undefined,
);
</script>

<template>
    <g>
        <rect
            :x="x - size / 2"
            :y="y - size / 2"
            :width="size"
            :height="size"
            rx="3"
            :fill="fill"
            :stroke="stroke"
            :stroke-width="kind === 'salida' ? 2.5 : 1.25"
        />
        <rect
            v-if="safe"
            :x="x - size / 2 + 1"
            :y="y - size / 2 + 1"
            :width="size - 2"
            :height="size - 2"
            rx="2"
            :fill="pattern"
            pointer-events="none"
        />
        <path
            v-if="kind === 'meta' || kind === 'goal'"
            :d="`M${x - size * 0.2} ${y + size * 0.2} L${x} ${y - size * 0.2} L${x + size * 0.2} ${y + size * 0.2}`"
            fill="none"
            stroke="var(--c-accent)"
            stroke-width="1.6"
            stroke-linecap="round"
            pointer-events="none"
        />
    </g>
</template>
