<script setup lang="ts">
import { computed } from 'vue';
import { boardPlayerColor } from '@/lib/playerStyle';

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

const fill = computed(() => boardPlayerColor(props.color));

/** Silueta de peón/figurita de parchís, escalada al radio de la casilla. */
const pawnPath = computed(() => {
    const x = props.cx;
    const y = props.cy;
    const s = props.r;
    const headR = s * 0.34;
    const headY = y - s * 0.5;
    const neckY = y - s * 0.16;
    const neckW = s * 0.2;
    const shoulderY = y + s * 0.08;
    const shoulderW = s * 0.46;
    const baseY = y + s * 0.8;
    const baseW = s * 0.74;

    return [
        `M ${x - neckW} ${neckY}`,
        `C ${x - neckW} ${headY + headR * 0.95}, ${x - headR} ${headY + headR * 0.95}, ${x - headR} ${headY}`,
        `A ${headR} ${headR} 0 1 1 ${x + headR} ${headY}`,
        `C ${x + headR} ${headY + headR * 0.95}, ${x + neckW} ${headY + headR * 0.95}, ${x + neckW} ${neckY}`,
        `Q ${x + shoulderW} ${shoulderY}, ${x + baseW * 0.68} ${baseY - s * 0.14}`,
        `Q ${x + baseW} ${baseY - s * 0.02}, ${x + baseW * 0.8} ${baseY}`,
        `L ${x - baseW * 0.8} ${baseY}`,
        `Q ${x - baseW} ${baseY - s * 0.02}, ${x - baseW * 0.68} ${baseY - s * 0.14}`,
        `Q ${x - shoulderW} ${shoulderY}, ${x - neckW} ${neckY}`,
        'Z',
    ].join(' ');
});

const fontSize = computed(() => Math.max(7, props.r * 0.58));
const numberY = computed(() => props.cy + props.r * 0.46);
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
        <path
            :d="pawnPath"
            :transform="`translate(0, ${r * 0.06})`"
            fill="var(--piece-shadow)"
            opacity="0.35"
        />
        <path
            :d="pawnPath"
            :fill="fill"
            stroke="var(--board-on-player)"
            stroke-width="1.8"
            stroke-linejoin="round"
        />
        <circle
            :cx="cx - r * 0.1"
            :cy="cy - r * 0.58"
            :r="r * 0.12"
            fill="var(--board-on-player)"
            opacity="0.22"
            pointer-events="none"
        />
        <text
            v-if="number !== undefined"
            :x="cx"
            :y="numberY"
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
