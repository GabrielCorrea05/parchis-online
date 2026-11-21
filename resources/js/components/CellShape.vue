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
        label?: number | string;
    }>(),
    {
        kind: 'normal',
        color: '',
        safe: false,
        patternId: 'hatch-safe',
        label: undefined,
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

// Las seguras llevan una estrella, no solo un color o una trama.
const starPoints = computed(() => {
    const radius = props.size * 0.17;
    const inner = radius * 0.45;
    const points: string[] = [];

    for (let i = 0; i < 10; i++) {
        const r = i % 2 === 0 ? radius : inner;
        const angle = -Math.PI / 2 + (i * Math.PI) / 5;
        points.push(
            `${(props.x + r * Math.cos(angle)).toFixed(2)},${(props.y + r * Math.sin(angle)).toFixed(2)}`,
        );
    }

    return points.join(' ');
});

const symbolFill = computed(() =>
    props.kind === 'salida' && props.color
        ? 'var(--c-on-player)'
        : 'var(--c-ink)',
);
const labelSize = computed(() => Math.max(9, Math.round(props.size * 0.3)));
const labelY = computed(() => props.y + props.size * 0.13);
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
        <polygon
            v-if="safe && kind !== 'salida'"
            :points="starPoints"
            :fill="symbolFill"
            pointer-events="none"
        />
        <text
            v-if="label !== undefined && kind === 'salida'"
            :x="x"
            :y="labelY"
            text-anchor="middle"
            :font-size="labelSize"
            font-weight="700"
            :fill="symbolFill"
            pointer-events="none"
        >
            {{ label }}
        </text>
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
