<script setup lang="ts">
import { useSound } from '@/lib/sound';

const { enabled, toggle } = useSound();
</script>

<template>
    <button
        type="button"
        class="btn btn-ghost h-11 w-11 !px-0"
        :aria-pressed="enabled"
        :title="
            enabled
                ? 'Sonido activado: silenciar'
                : 'Sonido desactivado: activar'
        "
        :aria-label="
            enabled
                ? 'Sonido activado: silenciar'
                : 'Sonido desactivado: activar'
        "
        @click="toggle"
    >
        <svg
            viewBox="0 0 24 24"
            class="h-5 w-5"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path d="M4 9v6h4l5 4V5L8 9H4Z" />
            <path
                v-if="enabled"
                d="M16 9a4 4 0 0 1 0 6M18.5 6.5a7.5 7.5 0 0 1 0 11"
            />
            <path v-else d="M17 9l4 6M21 9l-4 6" />
        </svg>
    </button>
</template>
