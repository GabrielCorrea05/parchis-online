<script setup lang="ts">
import { nextTick, ref, useId, watch, type ComponentPublicInstance } from 'vue';
import PlayerBadge from '@/components/PlayerBadge.vue';
import { playerLabel, playerShortLabel } from '@/lib/playerStyle';
import type { ColorOption } from '@/types';

const props = withDefaults(
    defineProps<{
        colors: ColorOption[];
        modelValue: string;
        error?: string;
    }>(),
    {
        error: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const labelId = useId();
const errorId = useId();
const buttons = ref<HTMLButtonElement[]>([]);
const focusedIndex = ref(
    Math.max(
        0,
        props.colors.findIndex((option) => option.name === props.modelValue),
    ),
);

watch(
    () => props.modelValue,
    (value) => {
        const index = props.colors.findIndex((option) => option.name === value);
        if (index >= 0) {
            focusedIndex.value = index;
        }
    },
);

function select(index: number): void {
    const option = props.colors[index];
    if (!option) {
        return;
    }

    focusedIndex.value = index;
    emit('update:modelValue', option.name);
}

async function focusOption(index: number): Promise<void> {
    if (props.colors.length === 0) {
        return;
    }

    const next = (index + props.colors.length) % props.colors.length;
    select(next);
    await nextTick();
    buttons.value[next]?.focus();
}

function onKeydown(event: KeyboardEvent, index: number): void {
    let next: number | null = null;

    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        next = index + 1;
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        next = index - 1;
    } else if (event.key === 'Home') {
        next = 0;
    } else if (event.key === 'End') {
        next = props.colors.length - 1;
    }

    if (next !== null) {
        event.preventDefault();
        void focusOption(next);
    }
}

function setButtonRef(
    element: Element | ComponentPublicInstance | null,
    index: number,
): void {
    if (element instanceof HTMLButtonElement) {
        buttons.value[index] = element;
    }
}
</script>

<template>
    <div>
        <span :id="labelId" class="label">Color y forma</span>
        <div
            role="radiogroup"
            :aria-labelledby="labelId"
            :aria-describedby="props.error ? errorId : undefined"
            :aria-invalid="props.error ? 'true' : undefined"
            class="flex flex-wrap gap-2"
        >
            <button
                v-for="(option, index) in props.colors"
                :key="option.name"
                :ref="(element) => setButtonRef(element, index)"
                type="button"
                role="radio"
                :aria-checked="props.modelValue === option.name"
                :tabindex="index === focusedIndex ? 0 : -1"
                class="color-option"
                :class="{
                    'color-option--selected': props.modelValue === option.name,
                }"
                @click="select(index)"
                @focus="focusedIndex = index"
                @keydown="onKeydown($event, index)"
            >
                <PlayerBadge
                    :color="option.name"
                    :label="playerLabel(option.name)"
                    :size="20"
                />
                <span>{{ playerShortLabel(option.name) }}</span>
            </button>
        </div>
        <p v-if="props.error" :id="errorId" class="error-text" role="alert">
            {{ props.error }}
        </p>
    </div>
</template>
