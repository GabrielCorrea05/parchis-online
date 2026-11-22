<script setup lang="ts">
import { computed } from 'vue';
import { playerColor, playerShape } from '@/lib/playerStyle';

const props = withDefaults(
    defineProps<{
        color: string;
        label?: string;
        number?: number;
        size?: number;
    }>(),
    {
        label: '',
        number: undefined,
        size: 20,
    },
);

const shape = computed(() => playerShape(props.color));
const color = computed(() => playerColor(props.color));

const points = computed(() => {
    switch (shape.value) {
        case 'triangle':
            return '12,2 22,21 2,21';
        case 'diamond':
            return '12,2 22,12 12,22 2,12';
        default:
            return '';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-2"
        :role="label ? 'img' : undefined"
        :title="label || undefined"
        :aria-label="label || undefined"
        :aria-hidden="label ? undefined : true"
    >
        <svg
            :width="size"
            :height="size"
            viewBox="0 0 24 24"
            class="player-badge shrink-0"
            :style="{ color: color }"
            aria-hidden="true"
        >
            <circle
                v-if="shape === 'circle'"
                cx="12"
                cy="12"
                r="10"
                fill="currentColor"
            />
            <rect
                v-else-if="shape === 'square'"
                x="2"
                y="2"
                width="20"
                height="20"
                rx="4"
                fill="currentColor"
            />
            <polygon v-else :points="points" fill="currentColor" />
            <text
                v-if="number !== undefined"
                x="12"
                y="16"
                text-anchor="middle"
                font-size="10"
                font-weight="800"
                fill="var(--c-on-player)"
            >
                {{ number }}
            </text>
        </svg>
        <slot />
    </span>
</template>
