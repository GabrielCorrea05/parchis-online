<script setup lang="ts">
import { computed } from 'vue';
import { boardPlayerColor, playerShape } from '@/lib/playerStyle';

const props = withDefaults(
    defineProps<{
        cx: number;
        cy: number;
        r: number;
        color: string;
        number?: number;
        legal?: boolean;
        active?: boolean;
        label?: string;
        interactive?: boolean;
    }>(),
    {
        number: undefined,
        legal: false,
        active: false,
        label: '',
        interactive: true,
    },
);

const emit = defineEmits<{
    select: [];
}>();

const shape = computed(() => playerShape(props.color));
const fill = computed(() => boardPlayerColor(props.color));

const polygonPoints = computed(() => {
    const r = props.r;
    switch (shape.value) {
        case 'triangle':
            return `${props.cx},${props.cy - r} ${props.cx + r * 0.92},${props.cy + r * 0.78} ${props.cx - r * 0.92},${props.cy + r * 0.78}`;
        case 'diamond':
            return `${props.cx},${props.cy - r} ${props.cx + r},${props.cy} ${props.cx},${props.cy + r} ${props.cx - r},${props.cy}`;
        case 'hexagon':
            return [0, 1, 2, 3, 4, 5]
                .map((i) => {
                    const angle = (Math.PI / 3) * i - Math.PI / 2;
                    return `${props.cx + Math.cos(angle) * r},${props.cy + Math.sin(angle) * r}`;
                })
                .join(' ');
        case 'star':
            return [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                .map((i) => {
                    const angle = (Math.PI / 5) * i - Math.PI / 2;
                    const radius = i % 2 === 0 ? r : r * 0.48;
                    return `${props.cx + Math.cos(angle) * radius},${props.cy + Math.sin(angle) * radius}`;
                })
                .join(' ');
        default:
            return '';
    }
});

const fontSize = computed(() =>
    Math.max(8, props.r * (shape.value === 'star' ? 0.65 : 0.9)),
);
</script>

<template>
    <g
        class="game-piece"
        :class="{ 'piece-legal': legal, 'piece-active': active }"
        :role="interactive ? 'button' : undefined"
        :tabindex="interactive ? (legal ? 0 : -1) : undefined"
        :data-legal-piece="interactive && legal ? 'true' : undefined"
        :aria-hidden="interactive ? undefined : true"
        :aria-label="
            interactive
                ? label || `Ficha ${number ?? ''} de ${color}`
                : undefined
        "
        @click="legal && emit('select')"
        @keydown.enter.prevent="legal && emit('select')"
        @keydown.space.prevent="legal && emit('select')"
    >
        <circle
            v-if="legal"
            :cx="cx"
            :cy="cy"
            :r="Math.max(r + 8, 26)"
            fill="transparent"
            pointer-events="all"
        />
        <circle
            class="piece-focus"
            :cx="cx"
            :cy="cy"
            :r="Math.max(r + 8, 26)"
            fill="none"
            stroke="var(--c-focus)"
            stroke-width="3"
        />
        <circle
            :cx="cx"
            :cy="cy + 1.5"
            :r="r"
            fill="var(--piece-shadow)"
            opacity="0.5"
        />
        <circle
            v-if="shape === 'circle'"
            :cx="cx"
            :cy="cy"
            :r="r"
            :fill="fill"
            stroke="var(--board-on-player)"
            stroke-width="2"
        />
        <rect
            v-else-if="shape === 'square'"
            :x="cx - r * 0.86"
            :y="cy - r * 0.86"
            :width="r * 1.72"
            :height="r * 1.72"
            rx="2"
            :fill="fill"
            stroke="var(--board-on-player)"
            stroke-width="2"
        />
        <polygon
            v-else
            :points="polygonPoints"
            :fill="fill"
            stroke="var(--board-on-player)"
            stroke-width="2"
            stroke-linejoin="round"
        />
        <text
            :x="cx"
            :y="cy + fontSize * 0.35"
            text-anchor="middle"
            :font-size="fontSize"
            font-weight="800"
            fill="var(--board-on-player)"
            pointer-events="none"
        >
            {{ number }}
        </text>
    </g>
</template>
