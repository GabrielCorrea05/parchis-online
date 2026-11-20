<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number | null;
        rolling?: boolean;
    }>(),
    {
        rolling: false,
    },
);

const pips: Record<number, [number, number][]> = {
    1: [[12, 12]],
    2: [
        [7, 7],
        [17, 17],
    ],
    3: [
        [7, 7],
        [12, 12],
        [17, 17],
    ],
    4: [
        [7, 7],
        [17, 7],
        [7, 17],
        [17, 17],
    ],
    5: [
        [7, 7],
        [17, 7],
        [12, 12],
        [7, 17],
        [17, 17],
    ],
    6: [
        [7, 7],
        [17, 7],
        [7, 12],
        [17, 12],
        [7, 17],
        [17, 17],
    ],
};

const dots = computed(() => pips[props.value ?? 0] ?? []);
const special = computed(() => props.value === 5 || props.value === 6);
</script>

<template>
    <div
        class="dice"
        role="img"
        :class="{ 'dice-rolling': rolling, 'dice-special': special }"
        :aria-label="value ? `Dado: ${value}` : 'Dado sin tirar'"
    >
        <svg viewBox="0 0 24 24" class="dice-cube" aria-hidden="true">
            <rect
                x="1"
                y="1"
                width="22"
                height="22"
                rx="5"
                fill="var(--c-surface)"
                stroke="var(--c-line)"
                stroke-width="1.5"
            />
            <circle
                v-for="([cx, cy], index) in dots"
                :key="index"
                :cx="cx"
                :cy="cy"
                r="2.4"
                :fill="special ? 'var(--c-accent)' : 'var(--c-ink)'"
            />
        </svg>
    </div>
</template>
